import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';

import {
  secureStorage,
  type SecureTokens,
  secureTokenStorage,
} from '../secure-storage';

// Mock implementation tracking
const mockSecureStore = SecureStore as jest.Mocked<typeof SecureStore>;
const mockCrypto = Crypto as jest.Mocked<typeof Crypto>;

describe('SecureStorage', () => {
  const testKey = 'test_key';
  const testValue = { data: 'test_value', number: 123 };

  beforeEach(() => {
    jest.clearAllMocks();
    // Reset mock implementations
    mockSecureStore.getItemAsync.mockResolvedValue(null);
    mockSecureStore.setItemAsync.mockResolvedValue(undefined);
    mockSecureStore.deleteItemAsync.mockResolvedValue(undefined);
  });

  describe('setItem', () => {
    it('should store item with correct key prefix and options', async () => {
      await secureStorage.setItem(testKey, testValue, {
        requireAuthentication: true,
        accessGroup: 'test-group',
      });

      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'checkright_secure_test_key',
        JSON.stringify(testValue),
        {
          requireAuthentication: true,
          accessGroup: 'test-group',
        }
      );
    });

    it('should use default options when none provided', async () => {
      await secureStorage.setItem(testKey, testValue);

      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'checkright_secure_test_key',
        JSON.stringify(testValue),
        {
          requireAuthentication: false,
          accessGroup: undefined,
        }
      );
    });

    it('should handle storage errors gracefully', async () => {
      const error = new Error('Storage failed');
      mockSecureStore.setItemAsync.mockRejectedValue(error);

      await expect(secureStorage.setItem(testKey, testValue)).rejects.toThrow(
        'Failed to securely store test_key'
      );
    });

    it('should handle complex objects correctly', async () => {
      const complexObject = {
        user: { id: 1, name: 'Test User' },
        settings: { theme: 'dark', notifications: true },
        timestamp: new Date().toISOString(),
      };

      await secureStorage.setItem(testKey, complexObject);

      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'checkright_secure_test_key',
        JSON.stringify(complexObject),
        expect.any(Object)
      );
    });
  });

  describe('getItem', () => {
    it('should retrieve and deserialize stored item', async () => {
      const serializedValue = JSON.stringify(testValue);
      mockSecureStore.getItemAsync.mockResolvedValue(serializedValue);

      const result = await secureStorage.getItem(testKey);

      expect(mockSecureStore.getItemAsync).toHaveBeenCalledWith(
        'checkright_secure_test_key'
      );
      expect(result).toEqual(testValue);
    });

    it('should return null when item does not exist', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(null);

      const result = await secureStorage.getItem(testKey);

      expect(result).toBeNull();
    });

    it('should handle retrieval errors gracefully', async () => {
      mockSecureStore.getItemAsync.mockRejectedValue(
        new Error('Retrieval failed')
      );

      const result = await secureStorage.getItem(testKey);

      expect(result).toBeNull();
    });

    it('should handle invalid JSON gracefully', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue('invalid json');

      const result = await secureStorage.getItem(testKey);

      expect(result).toBeNull();
    });
  });

  describe('removeItem', () => {
    it('should remove item with correct key', async () => {
      await secureStorage.removeItem(testKey);

      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith(
        'checkright_secure_test_key'
      );
    });

    it('should handle removal errors', async () => {
      mockSecureStore.deleteItemAsync.mockRejectedValue(
        new Error('Removal failed')
      );

      await expect(secureStorage.removeItem(testKey)).rejects.toThrow(
        'Failed to remove test_key from secure storage'
      );
    });
  });

  describe('hasItem', () => {
    it('should return true when item exists', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(JSON.stringify(testValue));

      const result = await secureStorage.hasItem(testKey);

      expect(result).toBe(true);
    });

    it('should return false when item does not exist', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(null);

      const result = await secureStorage.hasItem(testKey);

      expect(result).toBe(false);
    });

    it('should return false on retrieval error', async () => {
      mockSecureStore.getItemAsync.mockRejectedValue(new Error('Error'));

      const result = await secureStorage.hasItem(testKey);

      expect(result).toBe(false);
    });
  });

  describe('generateDeviceId', () => {
    it('should generate new device ID when none exists', async () => {
      // Mock no existing device ID
      mockSecureStore.getItemAsync.mockResolvedValue(null);

      // Mock crypto operations
      const mockRandomBytes = new Uint8Array([1, 2, 3, 4, 5]);
      mockCrypto.getRandomBytesAsync.mockResolvedValue(mockRandomBytes);
      mockCrypto.digestStringAsync.mockResolvedValue('abcdef123456');

      const deviceId = await secureStorage.generateDeviceId();

      expect(deviceId).toBe('device_abcdef123456');
      expect(mockCrypto.getRandomBytesAsync).toHaveBeenCalledWith(32);
      expect(mockCrypto.digestStringAsync).toHaveBeenCalledWith(
        Crypto.CryptoDigestAlgorithm.SHA256,
        '12345'
      );
      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'checkright_secure_device_id',
        '"device_abcdef123456"',
        { requireAuthentication: false, accessGroup: undefined }
      );
    });

    it('should return existing device ID when available', async () => {
      const existingDeviceId = 'device_existing123';
      mockSecureStore.getItemAsync.mockResolvedValue(`"${existingDeviceId}"`);

      const deviceId = await secureStorage.generateDeviceId();

      expect(deviceId).toBe(existingDeviceId);
      expect(mockCrypto.getRandomBytesAsync).not.toHaveBeenCalled();
      expect(mockSecureStore.setItemAsync).not.toHaveBeenCalled();
    });

    it('should handle device ID generation errors', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(null);
      mockCrypto.getRandomBytesAsync.mockRejectedValue(
        new Error('Crypto failed')
      );

      await expect(secureStorage.generateDeviceId()).rejects.toThrow(
        'Failed to generate secure device identifier'
      );
    });
  });

  describe('clearAll', () => {
    it('should remove all secure storage keys', async () => {
      await secureStorage.clearAll();

      const expectedKeys = [
        'checkright_secure_device_id',
        'checkright_secure_auth_tokens',
        'checkright_secure_biometric_key',
        'checkright_secure_device_secret',
        'checkright_secure_refresh_token',
        'checkright_secure_access_token',
      ];

      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledTimes(6);
      expectedKeys.forEach((key) => {
        expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith(key);
      });
    });

    it('should continue clearing even if individual removal fails', async () => {
      // Make first removal fail
      mockSecureStore.deleteItemAsync.mockRejectedValueOnce(
        new Error('First removal failed')
      );

      // Should not throw error and continue with other removals
      await expect(secureStorage.clearAll()).resolves.not.toThrow();

      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledTimes(6);
    });
  });
});

describe('secureTokenStorage', () => {
  const mockTokens: SecureTokens = {
    accessToken: 'access_token_123',
    refreshToken: 'refresh_token_456',
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: 'device_abc123',
    tokenType: 'Bearer',
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockSecureStore.getItemAsync.mockResolvedValue(null);
    mockSecureStore.setItemAsync.mockResolvedValue(undefined);
    mockSecureStore.deleteItemAsync.mockResolvedValue(undefined);
  });

  describe('setTokens', () => {
    it('should store tokens with biometric authentication required', async () => {
      await secureTokenStorage.setTokens(mockTokens);

      expect(mockSecureStore.setItemAsync).toHaveBeenCalledWith(
        'checkright_secure_auth_tokens',
        JSON.stringify(mockTokens),
        {
          requireAuthentication: true,
          accessGroup: undefined,
        }
      );
    });
  });

  describe('getTokens', () => {
    it('should retrieve stored tokens', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(
        JSON.stringify(mockTokens)
      );

      const result = await secureTokenStorage.getTokens();

      expect(mockSecureStore.getItemAsync).toHaveBeenCalledWith(
        'checkright_secure_auth_tokens'
      );
      expect(result).toEqual(mockTokens);
    });

    it('should return null when no tokens exist', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(null);

      const result = await secureTokenStorage.getTokens();

      expect(result).toBeNull();
    });
  });

  describe('removeTokens', () => {
    it('should remove authentication tokens', async () => {
      await secureTokenStorage.removeTokens();

      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith(
        'checkright_secure_auth_tokens'
      );
    });
  });

  describe('hasTokens', () => {
    it('should return true when tokens exist', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(
        JSON.stringify(mockTokens)
      );

      const result = await secureTokenStorage.hasTokens();

      expect(result).toBe(true);
    });

    it('should return false when no tokens exist', async () => {
      mockSecureStore.getItemAsync.mockResolvedValue(null);

      const result = await secureTokenStorage.hasTokens();

      expect(result).toBe(false);
    });
  });

  describe('Token Security Integration', () => {
    it('should properly handle token lifecycle', async () => {
      // Store tokens
      await secureTokenStorage.setTokens(mockTokens);
      expect(mockSecureStore.setItemAsync).toHaveBeenCalledTimes(1);

      // Verify tokens exist
      mockSecureStore.getItemAsync.mockResolvedValue(
        JSON.stringify(mockTokens)
      );
      const hasTokens = await secureTokenStorage.hasTokens();
      expect(hasTokens).toBe(true);

      // Retrieve tokens
      const retrievedTokens = await secureTokenStorage.getTokens();
      expect(retrievedTokens).toEqual(mockTokens);

      // Remove tokens
      await secureTokenStorage.removeTokens();
      expect(mockSecureStore.deleteItemAsync).toHaveBeenCalledWith(
        'checkright_secure_auth_tokens'
      );
    });
  });
});
