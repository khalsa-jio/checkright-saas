import * as Crypto from 'expo-crypto';

import type { SecureTokens } from '@/lib/secure-storage';
import { secureStorage, secureTokenStorage } from '@/lib/secure-storage';

import { client } from './common/client';

/**
 * Mobile Security API Client
 * Integrates with the Laravel backend's mobile security endpoints
 */

export interface DeviceRegistrationRequest {
  device_id: string;
  device_info: {
    platform: string;
    os_version: string;
    app_version: string;
    device_model: string;
    device_name?: string;
  };
}

export interface DeviceRegistrationResponse {
  message: string;
  device_id: string;
  device_secret: string;
  trust_status: string;
}

export interface TokenGenerationRequest {
  device_id: string;
}

export interface TokenGenerationResponse {
  message: string;
  tokens: {
    access_token: string;
    refresh_token: string;
    access_expires_in: number;
    refresh_expires_in: number;
    token_type: string;
    expires_at: string;
    refresh_expires_at: string;
  };
}

export interface RefreshTokenRequest {
  refresh_token: string;
  device_id: string;
  current_access_token?: string;
}

export interface TokenValidationResponse {
  valid: boolean;
  expired: boolean;
  expires_at: string;
  created_at: string;
  should_rotate: boolean;
  abilities: string[];
  token_name: string;
}

export interface DeviceInfo {
  device_id: string;
  device_info: object;
  is_trusted: boolean;
  trust_expires_at: string | null;
  last_used_at: string;
  created_at: string;
}

class MobileSecurityAPI {
  private readonly API_KEY =
    process.env.EXPO_PUBLIC_MOBILE_API_KEY || 'test-mobile-api-key-12345';
  private deviceId: string | null = null;
  private deviceSecret: string | null = null;

  constructor() {
    this.initializeDevice();
  }

  /**
   * Initialize device information
   */
  private async initializeDevice(): Promise<void> {
    try {
      this.deviceId = await secureStorage.generateDeviceId();
      this.deviceSecret = await secureStorage.getItem<string>('device_secret');
    } catch (error) {
      console.error('MobileSecurityAPI: Failed to initialize device', error);
    }
  }

  /**
   * Get device information for API requests
   */
  private async getDeviceInfo(): Promise<
    DeviceRegistrationRequest['device_info']
  > {
    // Note: In a real app, you'd use react-native-device-info
    // For now, we'll use placeholder values
    return {
      platform: 'ios', // or 'android'
      os_version: '17.0',
      app_version: '1.0.0',
      device_model: 'iPhone',
      device_name: 'User Device',
    };
  }

  /**
   * Generate request signature for API security
   */
  private async generateSignature(options: {
    method: string;
    url: string;
    timestamp: number;
    nonce: string;
    body?: string;
  }): Promise<string> {
    const { method, url, timestamp, nonce, body } = options;
    if (!this.deviceSecret) {
      throw new Error(
        'Device secret not available. Please register device first.'
      );
    }

    const payload = `${method}|${url}|${timestamp}|${nonce}${body ? `|${body}` : ''}`;
    const signature = await Crypto.digestStringAsync(
      Crypto.CryptoDigestAlgorithm.SHA256,
      `${payload}|${this.deviceSecret}`,
      { encoding: Crypto.CryptoEncoding.HEX }
    );

    return signature;
  }

  /**
   * Create authenticated request headers
   */
  private async createSecureHeaders(
    method: string,
    url: string,
    body?: any
  ): Promise<Record<string, string>> {
    const timestamp = Math.floor(Date.now() / 1000);
    const nonce = await Crypto.getRandomBytesAsync(16).then((bytes) =>
      Array.from(bytes)
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('')
    );

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-API-Key': this.API_KEY,
      'X-Timestamp': timestamp.toString(),
      'X-Nonce': nonce,
    };

    if (this.deviceId) {
      headers['X-Device-Id'] = this.deviceId;
    }

    // Add signature if we have device secret
    if (this.deviceSecret) {
      const bodyString = body ? JSON.stringify(body) : undefined;
      const signature = await this.generateSignature({
        method,
        url,
        timestamp,
        nonce,
        body: bodyString,
      });
      headers['X-Signature'] = signature;
    }

    return headers;
  }

  /**
   * Register device with the backend
   */
  async registerDevice(): Promise<DeviceRegistrationResponse> {
    try {
      if (!this.deviceId) {
        await this.initializeDevice();
      }

      const deviceInfo = await this.getDeviceInfo();
      const requestData: DeviceRegistrationRequest = {
        device_id: this.deviceId!,
        device_info: deviceInfo,
      };

      const headers = await this.createSecureHeaders(
        'POST',
        '/api/mobile/devices/register',
        requestData
      );

      const response = await client.post<DeviceRegistrationResponse>(
        '/api/mobile/devices/register',
        requestData,
        { headers }
      );

      // Store device secret securely
      if (response.data.device_secret) {
        this.deviceSecret = response.data.device_secret;
        await secureStorage.setItem(
          'device_secret',
          response.data.device_secret
        );
      }

      return response.data;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Device registration failed', error);
      throw new Error(
        error.response?.data?.message || 'Device registration failed'
      );
    }
  }

  /**
   * Generate new token pair
   */
  async generateTokens(): Promise<SecureTokens> {
    try {
      if (!this.deviceId) {
        await this.initializeDevice();
      }

      const requestData: TokenGenerationRequest = {
        device_id: this.deviceId!,
      };

      const headers = await this.createSecureHeaders(
        'POST',
        '/api/mobile/tokens/generate',
        requestData
      );

      const response = await client.post<TokenGenerationResponse>(
        '/api/mobile/tokens/generate',
        requestData,
        { headers }
      );

      const tokens: SecureTokens = {
        accessToken: response.data.tokens.access_token,
        refreshToken: response.data.tokens.refresh_token,
        expiresAt: response.data.tokens.expires_at,
        refreshExpiresAt: response.data.tokens.refresh_expires_at,
        deviceId: this.deviceId!,
        tokenType: response.data.tokens.token_type,
      };

      // Store tokens securely
      await secureTokenStorage.setTokens(tokens);

      return tokens;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Token generation failed', error);
      throw new Error(
        error.response?.data?.message || 'Token generation failed'
      );
    }
  }

  /**
   * Refresh access token using refresh token
   */
  async refreshTokens(): Promise<SecureTokens> {
    try {
      const currentTokens = await secureTokenStorage.getTokens();
      if (!currentTokens) {
        throw new Error('No tokens available for refresh');
      }

      const requestData: RefreshTokenRequest = {
        refresh_token: currentTokens.refreshToken,
        device_id: currentTokens.deviceId,
        current_access_token: currentTokens.accessToken,
      };

      const headers = await this.createSecureHeaders(
        'POST',
        '/api/mobile/tokens/refresh',
        requestData
      );

      const response = await client.post<TokenGenerationResponse>(
        '/api/mobile/tokens/refresh',
        requestData,
        { headers }
      );

      const tokens: SecureTokens = {
        accessToken: response.data.tokens.access_token,
        refreshToken: response.data.tokens.refresh_token,
        expiresAt: response.data.tokens.expires_at,
        refreshExpiresAt: response.data.tokens.refresh_expires_at,
        deviceId: currentTokens.deviceId,
        tokenType: response.data.tokens.token_type,
      };

      // Update stored tokens
      await secureTokenStorage.setTokens(tokens);

      return tokens;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Token refresh failed', error);
      // If it's a validation error, re-throw as-is
      if (error.message === 'No tokens available for refresh') {
        throw error;
      }
      throw new Error(error.response?.data?.message || 'Token refresh failed');
    }
  }

  /**
   * Validate current access token
   */
  async validateToken(): Promise<TokenValidationResponse> {
    try {
      const tokens = await secureTokenStorage.getTokens();
      if (!tokens) {
        throw new Error('No tokens available for validation');
      }

      const headers = await this.createSecureHeaders(
        'GET',
        '/api/mobile/tokens/validate'
      );
      headers['Authorization'] = `Bearer ${tokens.accessToken}`;

      const response = await client.get<TokenValidationResponse>(
        '/api/mobile/tokens/validate',
        { headers }
      );

      return response.data;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Token validation failed', error);
      // If it's a validation error, re-throw as-is
      if (error.message === 'No tokens available for validation') {
        throw error;
      }
      throw new Error(
        error.response?.data?.message || 'Token validation failed'
      );
    }
  }

  /**
   * Check if current token should be rotated
   */
  async shouldRotateToken(): Promise<{
    should_rotate: boolean;
    token_expires_at: string;
    token_created_at: string;
  }> {
    try {
      const tokens = await secureTokenStorage.getTokens();
      if (!tokens) {
        throw new Error('No tokens available');
      }

      const headers = await this.createSecureHeaders(
        'GET',
        '/api/mobile/tokens/should-rotate'
      );
      headers['Authorization'] = `Bearer ${tokens.accessToken}`;

      const response = await client.get('/api/mobile/tokens/should-rotate', {
        headers,
      });

      return response.data;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Check rotation failed', error);
      // If it's a validation error, re-throw as-is
      if (error.message === 'No tokens available') {
        throw error;
      }
      throw new Error(
        error.response?.data?.message || 'Failed to check token rotation'
      );
    }
  }

  /**
   * Get token information
   */
  async getTokenInfo(): Promise<any> {
    try {
      if (!this.deviceId) {
        await this.initializeDevice();
      }

      const tokens = await secureTokenStorage.getTokens();
      if (!tokens) {
        throw new Error('No tokens available');
      }

      const headers = await this.createSecureHeaders(
        'GET',
        `/api/mobile/tokens/info?device_id=${this.deviceId}`
      );
      headers['Authorization'] = `Bearer ${tokens.accessToken}`;

      const response = await client.get(
        `/api/mobile/tokens/info?device_id=${this.deviceId}`,
        { headers }
      );

      return response.data;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Get token info failed', error);
      // If it's a validation error, re-throw as-is
      if (error.message === 'No tokens available') {
        throw error;
      }
      throw new Error(
        error.response?.data?.message || 'Failed to get token info'
      );
    }
  }

  /**
   * Revoke all tokens for the current device
   */
  async revokeDeviceTokens(): Promise<void> {
    try {
      if (!this.deviceId) {
        await this.initializeDevice();
      }

      const headers = await this.createSecureHeaders(
        'DELETE',
        '/api/mobile/tokens/device',
        {
          device_id: this.deviceId,
        }
      );

      await client.delete('/api/mobile/tokens/device', {
        headers,
        data: { device_id: this.deviceId },
      });

      // Clear local tokens
      await secureTokenStorage.removeTokens();
    } catch (error: any) {
      console.error('MobileSecurityAPI: Revoke device tokens failed', error);
      throw new Error(
        error.response?.data?.message || 'Failed to revoke device tokens'
      );
    }
  }

  /**
   * Get device information
   */
  async getDevices(): Promise<DeviceInfo[]> {
    try {
      const tokens = await secureTokenStorage.getTokens();
      if (!tokens) {
        throw new Error('No tokens available');
      }

      const headers = await this.createSecureHeaders(
        'GET',
        '/api/mobile/devices'
      );
      headers['Authorization'] = `Bearer ${tokens.accessToken}`;

      const response = await client.get<{ devices: DeviceInfo[] }>(
        '/api/mobile/devices',
        { headers }
      );

      return response.data.devices;
    } catch (error: any) {
      console.error('MobileSecurityAPI: Get devices failed', error);
      // If it's a validation error, re-throw as-is
      if (error.message === 'No tokens available') {
        throw error;
      }
      throw new Error(error.response?.data?.message || 'Failed to get devices');
    }
  }
}

export const mobileSecurityAPI = new MobileSecurityAPI();
