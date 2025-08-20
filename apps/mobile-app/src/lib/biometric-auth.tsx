import * as LocalAuthentication from 'expo-local-authentication';
import { Alert } from 'react-native';

/**
 * Biometric authentication integration for React Native
 * Supports Face ID, Touch ID, and fingerprint authentication
 */

export interface BiometricAuthResult {
  success: boolean;
  error?: string;
  biometricType?: LocalAuthentication.AuthenticationType;
}

export interface BiometricCapabilities {
  hasHardware: boolean;
  isEnrolled: boolean;
  availableTypes: LocalAuthentication.AuthenticationType[];
}

class BiometricAuth {
  /**
   * Check device biometric capabilities
   */
  async getCapabilities(): Promise<BiometricCapabilities> {
    try {
      const hasHardware = await LocalAuthentication.hasHardwareAsync();
      const isEnrolled = await LocalAuthentication.isEnrolledAsync();
      const availableTypes =
        await LocalAuthentication.supportedAuthenticationTypesAsync();

      return {
        hasHardware,
        isEnrolled,
        availableTypes,
      };
    } catch (error) {
      console.error('BiometricAuth: Failed to get capabilities', error);
      return {
        hasHardware: false,
        isEnrolled: false,
        availableTypes: [],
      };
    }
  }

  /**
   * Check if biometric authentication is available and configured
   */
  async isAvailable(): Promise<boolean> {
    try {
      const capabilities = await this.getCapabilities();
      return capabilities.hasHardware && capabilities.isEnrolled;
    } catch (error) {
      console.error('BiometricAuth: Error checking availability', error);
      return false;
    }
  }

  /**
   * Get human-readable description of available biometric types
   */
  async getBiometricDescription(): Promise<string> {
    try {
      const capabilities = await this.getCapabilities();

      if (!capabilities.hasHardware) {
        return 'No biometric hardware available';
      }

      if (!capabilities.isEnrolled) {
        return 'No biometric authentication enrolled';
      }

      const types = capabilities.availableTypes;

      if (
        types.includes(
          LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION
        )
      ) {
        return 'Face ID';
      }

      if (types.includes(LocalAuthentication.AuthenticationType.FINGERPRINT)) {
        return 'Fingerprint';
      }

      if (types.includes(LocalAuthentication.AuthenticationType.IRIS)) {
        return 'Iris scanning';
      }

      return 'Biometric authentication';
    } catch (error) {
      console.error('BiometricAuth: Error getting description', error);
      return 'Biometric authentication';
    }
  }

  /**
   * Authenticate user with biometrics
   */
  async authenticate(
    reason: string = 'Please verify your identity'
  ): Promise<BiometricAuthResult> {
    try {
      // Check if biometric authentication is available
      const isAvailable = await this.isAvailable();

      if (!isAvailable) {
        return {
          success: false,
          error: 'Biometric authentication is not available on this device',
        };
      }

      // Perform authentication
      const result = await LocalAuthentication.authenticateAsync({
        promptMessage: reason,
        cancelLabel: 'Cancel',
        fallbackLabel: 'Use passcode',
        disableDeviceFallback: false,
      });

      if (result.success) {
        return {
          success: true,
        };
      } else {
        return {
          success: false,
          error: result.error || 'Authentication failed',
        };
      }
    } catch (error) {
      console.error('BiometricAuth: Authentication error', error);
      return {
        success: false,
        error: 'Authentication failed due to an unexpected error',
      };
    }
  }

  /**
   * Authenticate with custom options
   */
  async authenticateWithOptions(
    options: LocalAuthentication.LocalAuthenticationOptions
  ): Promise<BiometricAuthResult> {
    try {
      const isAvailable = await this.isAvailable();

      if (!isAvailable) {
        return {
          success: false,
          error: 'Biometric authentication is not available',
        };
      }

      const result = await LocalAuthentication.authenticateAsync(options);

      return {
        success: result.success,
        error: result.success
          ? undefined
          : result.error || 'Authentication failed',
      };
    } catch (error) {
      console.error('BiometricAuth: Authentication error', error);
      return {
        success: false,
        error: 'Authentication failed due to an unexpected error',
      };
    }
  }

  /**
   * Show biometric prompt for sensitive operations
   */
  async promptForSensitiveOperation(operation: string): Promise<boolean> {
    try {
      const description = await this.getBiometricDescription();
      const result = await this.authenticate(
        `Use ${description} to ${operation}`
      );

      if (!result.success && result.error) {
        Alert.alert('Authentication Failed', result.error, [{ text: 'OK' }]);
      }

      return result.success;
    } catch (error) {
      console.error(
        'BiometricAuth: Error in sensitive operation prompt',
        error
      );
      return false;
    }
  }

  /**
   * Configure biometric authentication for the app
   */
  async setupBiometricAuth(): Promise<{ success: boolean; message: string }> {
    try {
      const capabilities = await this.getCapabilities();

      if (!capabilities.hasHardware) {
        return {
          success: false,
          message: 'This device does not support biometric authentication',
        };
      }

      if (!capabilities.isEnrolled) {
        return {
          success: false,
          message:
            'Please set up biometric authentication in your device settings first',
        };
      }

      // Test authentication
      const authResult = await this.authenticate(
        'Verify your identity to enable biometric authentication for this app'
      );

      if (authResult.success) {
        return {
          success: true,
          message: 'Biometric authentication successfully enabled',
        };
      } else {
        return {
          success: false,
          message:
            authResult.error || 'Failed to verify biometric authentication',
        };
      }
    } catch (error) {
      console.error('BiometricAuth: Setup error', error);
      return {
        success: false,
        message: 'Failed to set up biometric authentication',
      };
    }
  }
}

export const biometricAuth = new BiometricAuth();

// Helper hook for React components
export const useBiometricAuth = () => {
  const checkAvailability = async () => {
    return await biometricAuth.isAvailable();
  };

  const authenticate = async (reason?: string) => {
    return await biometricAuth.authenticate(reason);
  };

  const getCapabilities = async () => {
    return await biometricAuth.getCapabilities();
  };

  const getBiometricDescription = async () => {
    return await biometricAuth.getBiometricDescription();
  };

  return {
    checkAvailability,
    authenticate,
    getCapabilities,
    getBiometricDescription,
    promptForSensitiveOperation:
      biometricAuth.promptForSensitiveOperation.bind(biometricAuth),
    setupBiometricAuth: biometricAuth.setupBiometricAuth.bind(biometricAuth),
  };
};
