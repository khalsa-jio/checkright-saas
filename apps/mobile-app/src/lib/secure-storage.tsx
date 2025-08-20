import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';

/**
 * Secure storage implementation using iOS Keychain and Android Keystore
 * Provides encrypted storage for sensitive data like authentication tokens
 */

export interface SecureStorageOptions {
  requireAuthentication?: boolean;
  accessGroup?: string;
}

class SecureStorage {
  private keyPrefix = 'checkright_secure_';

  /**
   * Store data securely in the device's secure storage
   * @param key - Storage key
   * @param value - Value to store
   * @param options - Additional security options
   */
  async setItem<T>(
    key: string,
    value: T,
    options: SecureStorageOptions = {}
  ): Promise<void> {
    try {
      const serializedValue = JSON.stringify(value);
      const secureKey = this.keyPrefix + key;

      await SecureStore.setItemAsync(secureKey, serializedValue, {
        requireAuthentication: options.requireAuthentication || false,
        accessGroup: options.accessGroup,
      });
    } catch (error) {
      console.error('SecureStorage: Failed to store item', error);
      throw new Error(`Failed to securely store ${key}`);
    }
  }

  /**
   * Retrieve data from secure storage
   * @param key - Storage key
   * @returns Stored value or null if not found
   */
  async getItem<T>(key: string): Promise<T | null> {
    try {
      const secureKey = this.keyPrefix + key;
      const value = await SecureStore.getItemAsync(secureKey);

      if (value === null) return null;

      return JSON.parse(value) as T;
    } catch (error) {
      console.error('SecureStorage: Failed to retrieve item', error);
      return null;
    }
  }

  /**
   * Remove item from secure storage
   * @param key - Storage key
   */
  async removeItem(key: string): Promise<void> {
    try {
      const secureKey = this.keyPrefix + key;
      await SecureStore.deleteItemAsync(secureKey);
    } catch (error) {
      console.error('SecureStorage: Failed to remove item', error);
      throw new Error(`Failed to remove ${key} from secure storage`);
    }
  }

  /**
   * Check if an item exists in secure storage
   * @param key - Storage key
   * @returns true if item exists, false otherwise
   */
  async hasItem(key: string): Promise<boolean> {
    try {
      const value = await this.getItem(key);
      return value !== null;
    } catch (_error) {
      return false;
    }
  }

  /**
   * Generate a secure device fingerprint
   * @returns Unique device identifier
   */
  async generateDeviceId(): Promise<string> {
    try {
      // Check if we already have a device ID stored
      let deviceId = await this.getItem<string>('device_id');

      if (!deviceId) {
        // Generate a new secure device ID
        const randomBytes = await Crypto.getRandomBytesAsync(32);
        const hash = await Crypto.digestStringAsync(
          Crypto.CryptoDigestAlgorithm.SHA256,
          Array.from(randomBytes).join('')
        );
        deviceId = `device_${hash.substring(0, 32)}`;

        // Store the device ID securely
        await this.setItem('device_id', deviceId, {
          requireAuthentication: false,
        });
      }

      return deviceId;
    } catch (error) {
      console.error('SecureStorage: Failed to generate device ID', error);
      throw new Error('Failed to generate secure device identifier');
    }
  }

  /**
   * Clear all secure storage data (use with caution)
   */
  async clearAll(): Promise<void> {
    try {
      // Get all keys and remove them
      const keys = [
        'device_id',
        'auth_tokens',
        'biometric_key',
        'device_secret',
        'refresh_token',
        'access_token',
      ];

      for (const key of keys) {
        try {
          await this.removeItem(key);
        } catch (error) {
          // Continue even if individual removal fails
          console.warn(`Failed to remove ${key}:`, error);
        }
      }
    } catch (error) {
      console.error('SecureStorage: Failed to clear all data', error);
      throw new Error('Failed to clear secure storage');
    }
  }
}

export const secureStorage = new SecureStorage();

// Enhanced token storage with secure encryption
export interface SecureTokens {
  accessToken: string;
  refreshToken: string;
  expiresAt: string;
  refreshExpiresAt: string;
  deviceId: string;
  tokenType: string;
}

export const secureTokenStorage = {
  /**
   * Store authentication tokens securely
   */
  async setTokens(tokens: SecureTokens): Promise<void> {
    await secureStorage.setItem('auth_tokens', tokens, {
      requireAuthentication: true, // Require biometric/passcode for access
    });
  },

  /**
   * Retrieve authentication tokens
   */
  async getTokens(): Promise<SecureTokens | null> {
    return await secureStorage.getItem<SecureTokens>('auth_tokens');
  },

  /**
   * Remove authentication tokens
   */
  async removeTokens(): Promise<void> {
    await secureStorage.removeItem('auth_tokens');
  },

  /**
   * Check if tokens exist
   */
  async hasTokens(): Promise<boolean> {
    return await secureStorage.hasItem('auth_tokens');
  },
};
