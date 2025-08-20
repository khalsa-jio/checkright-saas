import Constants from 'expo-constants';
import * as Crypto from 'expo-crypto';
import * as Localization from 'expo-localization';
import { Dimensions, Platform } from 'react-native';

/**
 * Device fingerprinting for React Native mobile apps
 * Creates unique device identifiers for security purposes
 */

export interface DeviceFingerprint {
  deviceId: string;
  platform: string;
  platformVersion: string;
  deviceModel: string;
  screenDimensions: {
    width: number;
    height: number;
    scale: number;
  };
  locale: string;
  timezone: string;
  appVersion: string;
  fingerprint: string;
}

class DeviceFingerprintService {
  private cachedFingerprint: DeviceFingerprint | null = null;

  /**
   * Generate comprehensive device fingerprint
   */
  async generateFingerprint(): Promise<DeviceFingerprint> {
    if (this.cachedFingerprint) {
      return this.cachedFingerprint;
    }

    try {
      const { width, height, scale } = Dimensions.get('window');

      // Collect device information
      const deviceInfo = {
        platform: Platform.OS,
        platformVersion: Platform.Version.toString(),
        deviceModel: this.getDeviceModel(),
        screenDimensions: { width, height, scale },
        locale: Localization.getLocales()[0]?.languageTag || 'en-NZ',
        timezone: Localization.getCalendars()[0]?.timeZone || 'UTC',
        appVersion: Constants.expoConfig?.version || '1.0.0',
      };

      // Generate unique device ID if not exists
      const deviceId = await this.generateDeviceId();

      // Create fingerprint hash
      const fingerprintData = JSON.stringify({
        ...deviceInfo,
        deviceId,
        constants: {
          brand:
            Constants.platform?.ios?.model ||
            Constants.platform?.android?.manufacturer,
          buildNumber: Constants.expoConfig?.extra?.buildNumber || '1',
        },
      });

      const fingerprint = await Crypto.digestStringAsync(
        Crypto.CryptoDigestAlgorithm.SHA256,
        fingerprintData,
        { encoding: Crypto.CryptoEncoding.HEX }
      );

      const result: DeviceFingerprint = {
        deviceId,
        ...deviceInfo,
        fingerprint,
      };

      this.cachedFingerprint = result;
      return result;
    } catch (error) {
      console.error(
        'DeviceFingerprintService: Failed to generate fingerprint',
        error
      );
      throw new Error('Failed to generate device fingerprint');
    }
  }

  /**
   * Get device model information
   */
  private getDeviceModel(): string {
    if (Platform.OS === 'ios') {
      return Constants.platform?.ios?.model || 'iPhone';
    } else if (Platform.OS === 'android') {
      return Constants.platform?.android?.model || 'Android Device';
    }
    return 'Unknown Device';
  }

  /**
   * Generate or retrieve persistent device ID
   */
  private async generateDeviceId(): Promise<string> {
    try {
      // Use Expo Constants installationId as base
      const installationId = Constants.installationId || 'unknown';

      // Create additional entropy
      const entropy = [
        Platform.OS,
        Platform.Version,
        Dimensions.get('window').width,
        Dimensions.get('window').height,
        Localization.getLocales()[0]?.languageTag || 'en-NZ',
        Constants.expoConfig?.name || 'app',
      ].join('|');

      // Generate device ID
      const deviceIdString = `${installationId}|${entropy}`;
      const deviceId = await Crypto.digestStringAsync(
        Crypto.CryptoDigestAlgorithm.SHA256,
        deviceIdString,
        { encoding: Crypto.CryptoEncoding.HEX }
      );

      return `mobile_${deviceId.substring(0, 32)}`;
    } catch (error) {
      console.error(
        'DeviceFingerprintService: Failed to generate device ID',
        error
      );
      throw new Error('Failed to generate device ID');
    }
  }

  /**
   * Get simplified device info for API requests
   */
  async getDeviceInfo(): Promise<{
    platform: string;
    platform_version: string;
    device_model: string;
    app_version: string;
    screen_width: number;
    screen_height: number;
    locale: string;
    timezone: string;
  }> {
    const fingerprint = await this.generateFingerprint();

    return {
      platform: fingerprint.platform,
      platform_version: fingerprint.platformVersion,
      device_model: fingerprint.deviceModel,
      app_version: fingerprint.appVersion,
      screen_width: fingerprint.screenDimensions.width,
      screen_height: fingerprint.screenDimensions.height,
      locale: fingerprint.locale,
      timezone: fingerprint.timezone,
    };
  }

  /**
   * Generate security context for API requests
   */
  async generateSecurityContext(): Promise<{
    device_fingerprint: string;
    device_id: string;
    timestamp: number;
    security_hash: string;
  }> {
    try {
      const fingerprint = await this.generateFingerprint();
      const timestamp = Math.floor(Date.now() / 1000);

      // Create security hash
      const securityData = `${fingerprint.fingerprint}|${fingerprint.deviceId}|${timestamp}`;
      const securityHash = await Crypto.digestStringAsync(
        Crypto.CryptoDigestAlgorithm.SHA256,
        securityData,
        { encoding: Crypto.CryptoEncoding.HEX }
      );

      return {
        device_fingerprint: fingerprint.fingerprint,
        device_id: fingerprint.deviceId,
        timestamp,
        security_hash: securityHash,
      };
    } catch (error) {
      console.error(
        'DeviceFingerprintService: Failed to generate security context',
        error
      );
      throw new Error('Failed to generate security context');
    }
  }

  /**
   * Validate device fingerprint consistency
   */
  async validateFingerprint(previousFingerprint: string): Promise<boolean> {
    try {
      const currentFingerprint = await this.generateFingerprint();
      return currentFingerprint.fingerprint === previousFingerprint;
    } catch (error) {
      console.error(
        'DeviceFingerprintService: Failed to validate fingerprint',
        error
      );
      return false;
    }
  }

  /**
   * Clear cached fingerprint (force regeneration)
   */
  clearCache(): void {
    this.cachedFingerprint = null;
  }

  /**
   * Get minimal device identifier for logging/analytics
   */
  async getDeviceHash(): Promise<string> {
    try {
      const fingerprint = await this.generateFingerprint();
      // Return first 12 characters of fingerprint for privacy-safe logging
      return fingerprint.fingerprint.substring(0, 12);
    } catch (error) {
      console.error(
        'DeviceFingerprintService: Failed to get device hash',
        error
      );
      return 'unknown';
    }
  }
}

export const deviceFingerprintService = new DeviceFingerprintService();

// React hook for device fingerprinting
export const useDeviceFingerprint = () => {
  const generateFingerprint = async () => {
    return await deviceFingerprintService.generateFingerprint();
  };

  const getDeviceInfo = async () => {
    return await deviceFingerprintService.getDeviceInfo();
  };

  const generateSecurityContext = async () => {
    return await deviceFingerprintService.generateSecurityContext();
  };

  const getDeviceHash = async () => {
    return await deviceFingerprintService.getDeviceHash();
  };

  return {
    generateFingerprint,
    getDeviceInfo,
    generateSecurityContext,
    getDeviceHash,
  };
};
