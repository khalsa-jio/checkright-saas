import { renderHook, act } from '@testing-library/react-native';
import * as LocalAuthentication from 'expo-local-authentication';
import { Alert } from 'react-native';

import { 
  biometricAuth, 
  useBiometricAuth, 
  BiometricAuthResult, 
  BiometricCapabilities 
} from '../biometric-auth';

// Mock implementations
const mockLocalAuth = LocalAuthentication as jest.Mocked<typeof LocalAuthentication>;
const mockAlert = Alert as jest.Mocked<typeof Alert>;

describe('BiometricAuth', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Reset default mock values
    mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
    mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
    mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
      LocalAuthentication.AuthenticationType.FINGERPRINT
    ]);
    mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });
  });

  describe('getCapabilities', () => {
    it('should return device biometric capabilities', async () => {
      const expectedTypes = [
        LocalAuthentication.AuthenticationType.FINGERPRINT,
        LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION
      ];
      
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue(expectedTypes);

      const capabilities = await biometricAuth.getCapabilities();

      expect(capabilities).toEqual({
        hasHardware: true,
        isEnrolled: true,
        availableTypes: expectedTypes
      });
      expect(mockLocalAuth.hasHardwareAsync).toHaveBeenCalledTimes(1);
      expect(mockLocalAuth.isEnrolledAsync).toHaveBeenCalledTimes(1);
      expect(mockLocalAuth.supportedAuthenticationTypesAsync).toHaveBeenCalledTimes(1);
    });

    it('should handle capability check errors gracefully', async () => {
      mockLocalAuth.hasHardwareAsync.mockRejectedValue(new Error('Hardware check failed'));

      const capabilities = await biometricAuth.getCapabilities();

      expect(capabilities).toEqual({
        hasHardware: false,
        isEnrolled: false,
        availableTypes: []
      });
    });

    it('should return false capabilities when hardware not available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(false);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([]);

      const capabilities = await biometricAuth.getCapabilities();

      expect(capabilities.hasHardware).toBe(false);
      expect(capabilities.isEnrolled).toBe(false);
      expect(capabilities.availableTypes).toEqual([]);
    });
  });

  describe('isAvailable', () => {
    it('should return true when hardware and enrollment are available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);

      const isAvailable = await biometricAuth.isAvailable();

      expect(isAvailable).toBe(true);
    });

    it('should return false when hardware is not available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);

      const isAvailable = await biometricAuth.isAvailable();

      expect(isAvailable).toBe(false);
    });

    it('should return false when not enrolled', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(false);

      const isAvailable = await biometricAuth.isAvailable();

      expect(isAvailable).toBe(false);
    });

    it('should handle availability check errors', async () => {
      mockLocalAuth.hasHardwareAsync.mockRejectedValue(new Error('Availability check failed'));

      const isAvailable = await biometricAuth.isAvailable();

      expect(isAvailable).toBe(false);
    });
  });

  describe('getBiometricDescription', () => {
    it('should return "Face ID" for facial recognition', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
        LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION
      ]);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('Face ID');
    });

    it('should return "Fingerprint" for fingerprint authentication', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
        LocalAuthentication.AuthenticationType.FINGERPRINT
      ]);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('Fingerprint');
    });

    it('should return "Iris scanning" for iris authentication', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
        LocalAuthentication.AuthenticationType.IRIS
      ]);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('Iris scanning');
    });

    it('should return generic message when no hardware available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('No biometric hardware available');
    });

    it('should return enrollment message when not enrolled', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(false);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('No biometric authentication enrolled');
    });

    it('should handle description errors gracefully', async () => {
      mockLocalAuth.hasHardwareAsync.mockRejectedValue(new Error('Description error'));

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('No biometric hardware available');
    });

    it('should prioritize Face ID over other types', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
        LocalAuthentication.AuthenticationType.FINGERPRINT,
        LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION
      ]);

      const description = await biometricAuth.getBiometricDescription();

      expect(description).toBe('Face ID');
    });
  });

  describe('authenticate', () => {
    it('should successfully authenticate with default reason', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });

      const result = await biometricAuth.authenticate();

      expect(result.success).toBe(true);
      expect(result.error).toBeUndefined();
      expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith({
        promptMessage: 'Please verify your identity',
        cancelLabel: 'Cancel',
        fallbackLabel: 'Use passcode',
        disableDeviceFallback: false
      });
    });

    it('should authenticate with custom reason', async () => {
      const customReason = 'Verify to access sensitive data';
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });

      const result = await biometricAuth.authenticate(customReason);

      expect(result.success).toBe(true);
      expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith({
        promptMessage: customReason,
        cancelLabel: 'Cancel',
        fallbackLabel: 'Use passcode',
        disableDeviceFallback: false
      });
    });

    it('should fail when biometric authentication is not available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);

      const result = await biometricAuth.authenticate();

      expect(result.success).toBe(false);
      expect(result.error).toBe('Biometric authentication is not available on this device');
    });

    it('should handle authentication failure', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ 
        success: false, 
        error: 'User canceled authentication' 
      });

      const result = await biometricAuth.authenticate();

      expect(result.success).toBe(false);
      expect(result.error).toBe('User canceled authentication');
    });

    it('should handle authentication exception', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockRejectedValue(new Error('Authentication exception'));

      const result = await biometricAuth.authenticate();

      expect(result.success).toBe(false);
      expect(result.error).toBe('Authentication failed due to an unexpected error');
    });
  });

  describe('authenticateWithOptions', () => {
    const customOptions = {
      promptMessage: 'Custom prompt',
      cancelLabel: 'Cancel Authentication',
      fallbackLabel: 'Use PIN',
      disableDeviceFallback: true
    };

    it('should authenticate with custom options', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });

      const result = await biometricAuth.authenticateWithOptions(customOptions);

      expect(result.success).toBe(true);
      expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith(customOptions);
    });

    it('should fail with custom options when not available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);

      const result = await biometricAuth.authenticateWithOptions(customOptions);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Biometric authentication is not available');
    });

    it('should handle custom options authentication failure', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ 
        success: false, 
        error: 'Custom authentication failed' 
      });

      const result = await biometricAuth.authenticateWithOptions(customOptions);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Custom authentication failed');
    });
  });

  describe('promptForSensitiveOperation', () => {
    it('should prompt with operation-specific message', async () => {
      const operation = 'access financial data';
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
        LocalAuthentication.AuthenticationType.FINGERPRINT
      ]);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });

      const result = await biometricAuth.promptForSensitiveOperation(operation);

      expect(result).toBe(true);
      expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith({
        promptMessage: 'Use Fingerprint to access financial data',
        cancelLabel: 'Cancel',
        fallbackLabel: 'Use passcode',
        disableDeviceFallback: false
      });
    });

    it('should show alert on authentication failure', async () => {
      const operation = 'delete account';
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ 
        success: false, 
        error: 'Authentication failed' 
      });

      const result = await biometricAuth.promptForSensitiveOperation(operation);

      expect(result).toBe(false);
      expect(mockAlert.alert).toHaveBeenCalledWith(
        'Authentication Failed',
        'Authentication failed',
        [{ text: 'OK' }]
      );
    });

    it('should handle sensitive operation errors', async () => {
      mockLocalAuth.hasHardwareAsync.mockRejectedValue(new Error('Sensitive operation error'));

      const result = await biometricAuth.promptForSensitiveOperation('test operation');

      expect(result).toBe(false);
    });
  });

  describe('setupBiometricAuth', () => {
    it('should successfully setup biometric authentication', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });

      const result = await biometricAuth.setupBiometricAuth();

      expect(result.success).toBe(true);
      expect(result.message).toBe('Biometric authentication successfully enabled');
      expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith({
        promptMessage: 'Verify your identity to enable biometric authentication for this app',
        cancelLabel: 'Cancel',
        fallbackLabel: 'Use passcode',
        disableDeviceFallback: false
      });
    });

    it('should fail setup when no hardware available', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(false);

      const result = await biometricAuth.setupBiometricAuth();

      expect(result.success).toBe(false);
      expect(result.message).toBe('This device does not support biometric authentication');
    });

    it('should fail setup when not enrolled', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(false);

      const result = await biometricAuth.setupBiometricAuth();

      expect(result.success).toBe(false);
      expect(result.message).toBe('Please set up biometric authentication in your device settings first');
    });

    it('should fail setup when authentication fails', async () => {
      mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
      mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
      mockLocalAuth.authenticateAsync.mockResolvedValue({ 
        success: false, 
        error: 'Setup authentication failed' 
      });

      const result = await biometricAuth.setupBiometricAuth();

      expect(result.success).toBe(false);
      expect(result.message).toBe('Setup authentication failed');
    });

    it('should handle setup errors', async () => {
      mockLocalAuth.hasHardwareAsync.mockRejectedValue(new Error('Setup error'));

      const result = await biometricAuth.setupBiometricAuth();

      expect(result.success).toBe(false);
      expect(result.message).toBe('This device does not support biometric authentication');
    });
  });
});

describe('useBiometricAuth hook', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockLocalAuth.hasHardwareAsync.mockResolvedValue(true);
    mockLocalAuth.isEnrolledAsync.mockResolvedValue(true);
    mockLocalAuth.supportedAuthenticationTypesAsync.mockResolvedValue([
      LocalAuthentication.AuthenticationType.FINGERPRINT
    ]);
    mockLocalAuth.authenticateAsync.mockResolvedValue({ success: true });
  });

  it('should provide checkAvailability function', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    await act(async () => {
      const isAvailable = await result.current.checkAvailability();
      expect(isAvailable).toBe(true);
    });

    expect(mockLocalAuth.hasHardwareAsync).toHaveBeenCalled();
    expect(mockLocalAuth.isEnrolledAsync).toHaveBeenCalled();
  });

  it('should provide authenticate function', async () => {
    const { result } = renderHook(() => useBiometricAuth());
    const reason = 'Test authentication';

    let authResult: BiometricAuthResult;
    await act(async () => {
      authResult = await result.current.authenticate(reason);
    });

    expect(authResult!.success).toBe(true);
    expect(mockLocalAuth.authenticateAsync).toHaveBeenCalledWith({
      promptMessage: reason,
      cancelLabel: 'Cancel',
      fallbackLabel: 'Use passcode',
      disableDeviceFallback: false
    });
  });

  it('should provide getCapabilities function', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    let capabilities: BiometricCapabilities;
    await act(async () => {
      capabilities = await result.current.getCapabilities();
    });

    expect(capabilities!).toEqual({
      hasHardware: true,
      isEnrolled: true,
      availableTypes: [LocalAuthentication.AuthenticationType.FINGERPRINT]
    });
  });

  it('should provide getBiometricDescription function', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    let description: string;
    await act(async () => {
      description = await result.current.getBiometricDescription();
    });

    expect(description!).toBe('Fingerprint');
  });

  it('should provide promptForSensitiveOperation function', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    let operationResult: boolean;
    await act(async () => {
      operationResult = await result.current.promptForSensitiveOperation('test operation');
    });

    expect(operationResult!).toBe(true);
  });

  it('should provide setupBiometricAuth function', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    let setupResult: { success: boolean; message: string };
    await act(async () => {
      setupResult = await result.current.setupBiometricAuth();
    });

    expect(setupResult!.success).toBe(true);
    expect(setupResult!.message).toBe('Biometric authentication successfully enabled');
  });

  it('should handle hook authentication with different scenarios', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    // Test successful authentication
    await act(async () => {
      const authResult = await result.current.authenticate('Access secure data');
      expect(authResult.success).toBe(true);
    });

    // Test failed authentication
    mockLocalAuth.authenticateAsync.mockResolvedValueOnce({ 
      success: false, 
      error: 'User cancelled' 
    });

    await act(async () => {
      const authResult = await result.current.authenticate('Access secure data');
      expect(authResult.success).toBe(false);
      expect(authResult.error).toBe('User cancelled');
    });
  });

  it('should handle hook availability checks', async () => {
    const { result } = renderHook(() => useBiometricAuth());

    // Test when available
    await act(async () => {
      const isAvailable = await result.current.checkAvailability();
      expect(isAvailable).toBe(true);
    });

    // Test when not available
    mockLocalAuth.hasHardwareAsync.mockResolvedValueOnce(false);

    await act(async () => {
      const isAvailable = await result.current.checkAvailability();
      expect(isAvailable).toBe(false);
    });
  });
});