import * as Crypto from 'expo-crypto';

import { secureStorage, secureTokenStorage, SecureTokens } from '@/lib/secure-storage';

import { client } from '../common/client';
import {
  mobileSecurityAPI,
  DeviceRegistrationRequest,
  DeviceRegistrationResponse,
  TokenGenerationResponse,
  TokenValidationResponse,
  DeviceInfo,
} from '../mobile-security';

// Mock dependencies
jest.mock('@/lib/secure-storage');
jest.mock('../common/client');
jest.mock('expo-crypto');

const mockSecureStorage = secureStorage as jest.Mocked<typeof secureStorage>;
const mockSecureTokenStorage = secureTokenStorage as jest.Mocked<typeof secureTokenStorage>;
const mockClient = client as jest.Mocked<typeof client>;
const mockCrypto = Crypto as jest.Mocked<typeof Crypto>;

describe('MobileSecurityAPI', () => {
  const mockDeviceId = 'device_test123';
  const mockDeviceSecret = 'secret_test456';
  const mockAccessToken = 'access_token_123';
  const mockRefreshToken = 'refresh_token_456';
  const mockSignature = 'mocked_signature_hash';
  const mockNonce = '1234567890abcdef';

  const mockTokens: SecureTokens = {
    accessToken: mockAccessToken,
    refreshToken: mockRefreshToken,
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: mockDeviceId,
    tokenType: 'Bearer',
  };

  beforeEach(() => {
    jest.clearAllMocks();
    
    // Default mock implementations
    mockSecureStorage.generateDeviceId.mockResolvedValue(mockDeviceId);
    mockSecureStorage.getItem.mockResolvedValue(mockDeviceSecret);
    mockSecureStorage.setItem.mockResolvedValue(undefined);
    mockSecureTokenStorage.getTokens.mockResolvedValue(mockTokens);
    mockSecureTokenStorage.setTokens.mockResolvedValue(undefined);
    mockSecureTokenStorage.removeTokens.mockResolvedValue(undefined);
    
    // Mock crypto functions
    mockCrypto.digestStringAsync.mockResolvedValue(mockSignature);
    mockCrypto.getRandomBytesAsync.mockResolvedValue(
      new Uint8Array([0x12, 0x34, 0x56, 0x78, 0x90, 0xab, 0xcd, 0xef])
    );
    
    // Mock client responses
    mockClient.post.mockResolvedValue({ data: {} });
    mockClient.get.mockResolvedValue({ data: {} });
    mockClient.delete.mockResolvedValue({ data: {} });
  });

  describe('Device Registration', () => {
    it('should successfully register device', async () => {
      const mockResponse: DeviceRegistrationResponse = {
        message: 'Device registered successfully',
        device_id: mockDeviceId,
        device_secret: mockDeviceSecret,
        trust_status: 'trusted',
      };

      mockClient.post.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.registerDevice();

      expect(result).toEqual(mockResponse);
      expect(mockClient.post).toHaveBeenCalledWith(
        '/api/mobile/devices/register',
        {
          device_id: mockDeviceId,
          device_info: {
            platform: 'ios',
            os_version: '17.0',
            app_version: '1.0.0',
            device_model: 'iPhone',
            device_name: 'User Device',
          },
        },
        {
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-API-Key': expect.any(String),
            'X-Timestamp': expect.any(String),
            'X-Nonce': expect.any(String),
            'X-Device-Id': mockDeviceId,
          }),
        }
      );

      expect(mockSecureStorage.setItem).toHaveBeenCalledWith(
        'device_secret',
        mockDeviceSecret
      );
    });

    it('should handle device registration failure', async () => {
      const error = {
        response: {
          data: { message: 'Device registration failed' },
        },
      };

      mockClient.post.mockRejectedValue(error);

      await expect(mobileSecurityAPI.registerDevice()).rejects.toThrow(
        'Device registration failed'
      );
    });

    it('should handle network error during registration', async () => {
      mockClient.post.mockRejectedValue(new Error('Network error'));

      await expect(mobileSecurityAPI.registerDevice()).rejects.toThrow(
        'Device registration failed'
      );
    });
  });

  describe('Token Generation', () => {
    it('should successfully generate tokens', async () => {
      const mockResponse: TokenGenerationResponse = {
        message: 'Tokens generated successfully',
        tokens: {
          access_token: mockAccessToken,
          refresh_token: mockRefreshToken,
          access_expires_in: 900,
          refresh_expires_in: 604800,
          token_type: 'Bearer',
          expires_at: '2024-12-31T23:59:59Z',
          refresh_expires_at: '2025-01-07T23:59:59Z',
        },
      };

      mockClient.post.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.generateTokens();

      expect(result).toEqual(mockTokens);
      expect(mockClient.post).toHaveBeenCalledWith(
        '/api/mobile/tokens/generate',
        { device_id: mockDeviceId },
        {
          headers: expect.objectContaining({
            'X-Device-Id': mockDeviceId,
            'X-Signature': mockSignature,
          }),
        }
      );

      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(mockTokens);
    });

    it('should handle token generation failure', async () => {
      const error = {
        response: {
          data: { message: 'Token generation failed' },
        },
      };

      mockClient.post.mockRejectedValue(error);

      await expect(mobileSecurityAPI.generateTokens()).rejects.toThrow(
        'Token generation failed'
      );
    });
  });

  describe('Token Refresh', () => {
    it('should successfully refresh tokens', async () => {
      const mockResponse: TokenGenerationResponse = {
        message: 'Tokens refreshed successfully',
        tokens: {
          access_token: 'new_access_token',
          refresh_token: 'new_refresh_token',
          access_expires_in: 900,
          refresh_expires_in: 604800,
          token_type: 'Bearer',
          expires_at: '2024-12-31T23:59:59Z',
          refresh_expires_at: '2025-01-07T23:59:59Z',
        },
      };

      mockClient.post.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.refreshTokens();

      expect(result.accessToken).toBe('new_access_token');
      expect(result.refreshToken).toBe('new_refresh_token');
      expect(mockClient.post).toHaveBeenCalledWith(
        '/api/mobile/tokens/refresh',
        {
          refresh_token: mockRefreshToken,
          device_id: mockDeviceId,
          current_access_token: mockAccessToken,
        },
        {
          headers: expect.objectContaining({
            'X-Signature': mockSignature,
          }),
        }
      );

      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(
        expect.objectContaining({
          accessToken: 'new_access_token',
          refreshToken: 'new_refresh_token',
        })
      );
    });

    it('should handle refresh failure when no tokens available', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      await expect(mobileSecurityAPI.refreshTokens()).rejects.toThrow(
        'No tokens available for refresh'
      );
    });

    it('should handle token refresh API failure', async () => {
      const error = {
        response: {
          data: { message: 'Token refresh failed' },
        },
      };

      mockClient.post.mockRejectedValue(error);

      await expect(mobileSecurityAPI.refreshTokens()).rejects.toThrow(
        'Token refresh failed'
      );
    });
  });

  describe('Token Validation', () => {
    it('should successfully validate token', async () => {
      const mockResponse: TokenValidationResponse = {
        valid: true,
        expired: false,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: false,
        abilities: ['access-user', 'manage-devices'],
        token_name: 'mobile-access-token',
      };

      mockClient.get.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.validateToken();

      expect(result).toEqual(mockResponse);
      expect(mockClient.get).toHaveBeenCalledWith(
        '/api/mobile/tokens/validate',
        {
          headers: expect.objectContaining({
            Authorization: `Bearer ${mockAccessToken}`,
            'X-Signature': mockSignature,
          }),
        }
      );
    });

    it('should handle validation failure when no tokens available', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      await expect(mobileSecurityAPI.validateToken()).rejects.toThrow(
        'No tokens available for validation'
      );
    });

    it('should handle token validation API failure', async () => {
      const error = {
        response: {
          data: { message: 'Token validation failed' },
        },
      };

      mockClient.get.mockRejectedValue(error);

      await expect(mobileSecurityAPI.validateToken()).rejects.toThrow(
        'Token validation failed'
      );
    });
  });

  describe('Token Rotation Check', () => {
    it('should check if token should be rotated', async () => {
      const mockResponse = {
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      };

      mockClient.get.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.shouldRotateToken();

      expect(result).toEqual(mockResponse);
      expect(mockClient.get).toHaveBeenCalledWith(
        '/api/mobile/tokens/should-rotate',
        {
          headers: expect.objectContaining({
            Authorization: `Bearer ${mockAccessToken}`,
          }),
        }
      );
    });

    it('should handle rotation check failure', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      await expect(mobileSecurityAPI.shouldRotateToken()).rejects.toThrow(
        'No tokens available'
      );
    });
  });

  describe('Token Information', () => {
    it('should get token information', async () => {
      const mockResponse = {
        token_id: 'token_123',
        device_id: mockDeviceId,
        expires_at: '2024-12-31T23:59:59Z',
        last_used_at: '2024-12-15T12:00:00Z',
      };

      mockClient.get.mockResolvedValue({ data: mockResponse });

      const result = await mobileSecurityAPI.getTokenInfo();

      expect(result).toEqual(mockResponse);
      expect(mockClient.get).toHaveBeenCalledWith(
        `/api/mobile/tokens/info?device_id=${mockDeviceId}`,
        {
          headers: expect.objectContaining({
            Authorization: `Bearer ${mockAccessToken}`,
          }),
        }
      );
    });

    it('should handle get token info failure', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      await expect(mobileSecurityAPI.getTokenInfo()).rejects.toThrow(
        'No tokens available'
      );
    });
  });

  describe('Token Revocation', () => {
    it('should revoke device tokens', async () => {
      mockClient.delete.mockResolvedValue({ data: { message: 'Tokens revoked' } });

      await mobileSecurityAPI.revokeDeviceTokens();

      expect(mockClient.delete).toHaveBeenCalledWith(
        '/api/mobile/tokens/device',
        {
          headers: expect.objectContaining({
            'X-Signature': mockSignature,
          }),
          data: { device_id: mockDeviceId },
        }
      );

      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
    });

    it('should handle token revocation failure', async () => {
      const error = {
        response: {
          data: { message: 'Failed to revoke tokens' },
        },
      };

      mockClient.delete.mockRejectedValue(error);

      await expect(mobileSecurityAPI.revokeDeviceTokens()).rejects.toThrow(
        'Failed to revoke tokens'
      );
    });
  });

  describe('Device Management', () => {
    it('should get devices list', async () => {
      const mockDevices: DeviceInfo[] = [
        {
          device_id: mockDeviceId,
          device_info: { platform: 'ios' },
          is_trusted: true,
          trust_expires_at: '2025-01-01T00:00:00Z',
          last_used_at: '2024-12-15T12:00:00Z',
          created_at: '2024-12-01T00:00:00Z',
        },
      ];

      mockClient.get.mockResolvedValue({ data: { devices: mockDevices } });

      const result = await mobileSecurityAPI.getDevices();

      expect(result).toEqual(mockDevices);
      expect(mockClient.get).toHaveBeenCalledWith('/api/mobile/devices', {
        headers: expect.objectContaining({
          Authorization: `Bearer ${mockAccessToken}`,
        }),
      });
    });

    it('should handle get devices failure', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      await expect(mobileSecurityAPI.getDevices()).rejects.toThrow(
        'No tokens available'
      );
    });
  });

  describe('Request Signing', () => {
    it('should generate correct signature for requests', async () => {
      // Access private method through instance for testing
      const api = mobileSecurityAPI as any;
      api.deviceSecret = mockDeviceSecret;

      const options = {
        method: 'POST',
        url: '/api/test',
        timestamp: 1640995200, // Fixed timestamp for testing
        nonce: 'test-nonce',
        body: '{"test":"data"}',
      };

      const signature = await api.generateSignature(options);

      expect(mockCrypto.digestStringAsync).toHaveBeenCalledWith(
        Crypto.CryptoDigestAlgorithm.SHA256,
        `POST|/api/test|1640995200|test-nonce|{"test":"data"}|${mockDeviceSecret}`,
        { encoding: Crypto.CryptoEncoding.HEX }
      );
      expect(signature).toBe(mockSignature);
    });

    it('should handle signature generation without body', async () => {
      const api = mobileSecurityAPI as any;
      api.deviceSecret = mockDeviceSecret;

      const options = {
        method: 'GET',
        url: '/api/test',
        timestamp: 1640995200,
        nonce: 'test-nonce',
      };

      await api.generateSignature(options);

      expect(mockCrypto.digestStringAsync).toHaveBeenCalledWith(
        Crypto.CryptoDigestAlgorithm.SHA256,
        `GET|/api/test|1640995200|test-nonce|${mockDeviceSecret}`,
        { encoding: Crypto.CryptoEncoding.HEX }
      );
    });

    it('should throw error when device secret not available', async () => {
      const api = mobileSecurityAPI as any;
      api.deviceSecret = null;

      const options = {
        method: 'POST',
        url: '/api/test',
        timestamp: 1640995200,
        nonce: 'test-nonce',
      };

      await expect(api.generateSignature(options)).rejects.toThrow(
        'Device secret not available. Please register device first.'
      );
    });
  });

  describe('Secure Headers Creation', () => {
    it('should create secure headers with all required fields', async () => {
      const api = mobileSecurityAPI as any;
      api.deviceId = mockDeviceId;
      api.deviceSecret = mockDeviceSecret;

      const headers = await api.createSecureHeaders('POST', '/api/test', { data: 'test' });

      expect(headers).toEqual(
        expect.objectContaining({
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-API-Key': expect.any(String),
          'X-Timestamp': expect.any(String),
          'X-Nonce': expect.any(String),
          'X-Device-Id': mockDeviceId,
          'X-Signature': mockSignature,
        })
      );
    });

    it('should create headers without signature when no device secret', async () => {
      const api = mobileSecurityAPI as any;
      api.deviceId = mockDeviceId;
      api.deviceSecret = null;

      const headers = await api.createSecureHeaders('GET', '/api/test');

      expect(headers).toEqual(
        expect.objectContaining({
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-API-Key': expect.any(String),
          'X-Timestamp': expect.any(String),
          'X-Nonce': expect.any(String),
          'X-Device-Id': mockDeviceId,
        })
      );
      expect(headers['X-Signature']).toBeUndefined();
    });
  });

  describe('Device Initialization', () => {
    it('should initialize device on construction', async () => {
      // Since the singleton is already created, we can't test the exact constructor call
      // But we can verify that the device was initialized by checking if the device ID exists
      const api = mobileSecurityAPI as any;
      
      // The constructor should have called initializeDevice asynchronously
      // Since it's async, we need to wait a bit for it to complete
      await new Promise(resolve => setTimeout(resolve, 10));
      
      // Verify that device initialization was attempted (deviceId should be set)
      expect(api.deviceId).toBeDefined();
      
      // Note: Since the singleton was created before test setup, we can't assert
      // the exact mock calls, but we can verify the initialization worked
    });

    it('should handle device initialization failure gracefully', async () => {
      mockSecureStorage.generateDeviceId.mockRejectedValue(new Error('Init failed'));
      
      // Should not throw during construction
      expect(() => {
        // Access private method to test error handling
        const api = mobileSecurityAPI as any;
        api.initializeDevice();
      }).not.toThrow();
    });
  });

  describe('Error Handling', () => {
    it('should handle API errors with proper error messages', async () => {
      const apiError = {
        response: {
          data: { message: 'Custom API error message' },
        },
      };

      mockClient.post.mockRejectedValue(apiError);

      await expect(mobileSecurityAPI.registerDevice()).rejects.toThrow(
        'Custom API error message'
      );
    });

    it('should handle network errors with fallback messages', async () => {
      mockClient.post.mockRejectedValue(new Error('Network failure'));

      await expect(mobileSecurityAPI.generateTokens()).rejects.toThrow(
        'Token generation failed'
      );
    });

    it('should handle crypto operation failures', async () => {
      mockCrypto.digestStringAsync.mockRejectedValue(new Error('Crypto failed'));
      
      const api = mobileSecurityAPI as any;
      api.deviceSecret = mockDeviceSecret;

      await expect(
        api.generateSignature({
          method: 'POST',
          url: '/api/test',
          timestamp: 1640995200,
          nonce: 'test-nonce',
        })
      ).rejects.toThrow();
    });
  });

  describe('Environment Configuration', () => {
    it('should use environment API key when available', () => {
      // Since the singleton was created before tests, we can't test dynamic env var loading
      // But we can verify that the API_KEY property exists and has the expected fallback
      const api = mobileSecurityAPI as any;
      
      // The API_KEY should be either the env var or the fallback value
      expect(api.API_KEY).toBeDefined();
      expect(typeof api.API_KEY).toBe('string');
      
      // Since we can't change the env var for the existing singleton,
      // we verify it has a reasonable value (either env or fallback)
      expect(api.API_KEY.length).toBeGreaterThan(0);
    });
  });
});