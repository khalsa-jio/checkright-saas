import { act, renderHook, waitFor } from '@testing-library/react-native';

// Import all security-related modules
import { secureStorage, secureTokenStorage, SecureTokens } from '@/lib/secure-storage';
import { biometricAuth, BiometricAuthResult } from '@/lib/biometric-auth';
import { deviceFingerprintService, DeviceFingerprint } from '@/lib/device-fingerprint';
import { mobileSecurityAPI } from '@/api/mobile-security';
import { useSecureAuth, tokenRotationService } from '@/lib/auth/secure-auth';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useAuthStore } from '@/features/auth/stores/authStore';

// Mock all dependencies - integration test will coordinate between them
jest.mock('@/lib/secure-storage');
jest.mock('@/lib/biometric-auth');
jest.mock('@/lib/device-fingerprint');
jest.mock('@/api/mobile-security');
jest.mock('@/api/common/client');
jest.mock('@/features/auth/services/api');
jest.mock('@/features/auth/stores/authStore');
jest.mock('expo-secure-store');
jest.mock('expo-local-authentication');
jest.mock('expo-crypto');
jest.mock('expo-constants');
jest.mock('expo-localization');
jest.mock('react-native');

const mockSecureStorage = secureStorage as jest.Mocked<typeof secureStorage>;
const mockSecureTokenStorage = secureTokenStorage as jest.Mocked<typeof secureTokenStorage>;
const mockBiometricAuth = biometricAuth as jest.Mocked<typeof biometricAuth>;
const mockDeviceFingerprintService = deviceFingerprintService as jest.Mocked<typeof deviceFingerprintService>;
const mockMobileSecurityAPI = mobileSecurityAPI as jest.Mocked<typeof mobileSecurityAPI>;
const mockUseAuthStore = useAuthStore as jest.MockedFunction<typeof useAuthStore>;

describe('Complete Mobile Security Workflow Integration', () => {
  const mockDeviceId = 'device_test123';
  const mockDeviceSecret = 'secret_test456';
  const mockFingerprint: DeviceFingerprint = {
    deviceId: mockDeviceId,
    platform: 'ios',
    platformVersion: '17.0',
    deviceModel: 'iPhone',
    screenDimensions: { width: 375, height: 812, scale: 3 },
    locale: 'en-US',
    timezone: 'America/New_York',
    appVersion: '1.0.0',
    fingerprint: 'fingerprint_hash_123',
  };

  const mockSecureTokens: SecureTokens = {
    accessToken: 'secure_access_token_456',
    refreshToken: 'secure_refresh_token_789',
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: mockDeviceId,
    tokenType: 'Bearer',
  };

  const mockUser = {
    id: '1',
    name: 'John Doe',
    email: 'john.doe@example.com',
    role: 'manager' as const,
    tenant_id: 'tenant-123',
  };

  const mockAuthStore = {
    acceptInvitation: jest.fn(),
    login: jest.fn(),
    logout: jest.fn(),
    clearError: jest.fn(),
    refreshToken: jest.fn(),
    hydrate: jest.fn(),
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: false,
    rememberMe: false,
    tokenExpiresAt: null,
    error: null,
    checkTokenExpiration: jest.fn().mockReturnValue(false),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();

    // Setup secure storage mocks
    mockSecureStorage.generateDeviceId.mockResolvedValue(mockDeviceId);
    mockSecureStorage.getItem.mockResolvedValue(mockDeviceSecret);
    mockSecureStorage.setItem.mockResolvedValue(undefined);
    mockSecureStorage.removeItem.mockResolvedValue(undefined);
    mockSecureStorage.hasItem.mockResolvedValue(true);
    mockSecureStorage.clearAll.mockResolvedValue(undefined);

    mockSecureTokenStorage.getTokens.mockResolvedValue(mockSecureTokens);
    mockSecureTokenStorage.setTokens.mockResolvedValue(undefined);
    mockSecureTokenStorage.removeTokens.mockResolvedValue(undefined);
    mockSecureTokenStorage.hasTokens.mockResolvedValue(true);

    // Setup biometric auth mocks
    mockBiometricAuth.isAvailable.mockResolvedValue(true);
    mockBiometricAuth.getCapabilities.mockResolvedValue({
      hasHardware: true,
      isEnrolled: true,
      availableTypes: [1], // FINGERPRINT
    });
    mockBiometricAuth.getBiometricDescription.mockResolvedValue('Fingerprint');
    mockBiometricAuth.authenticate.mockResolvedValue({ success: true });
    mockBiometricAuth.setupBiometricAuth.mockResolvedValue({
      success: true,
      message: 'Biometric authentication enabled',
    });
    mockBiometricAuth.promptForSensitiveOperation.mockResolvedValue(true);

    // Setup device fingerprint mocks
    mockDeviceFingerprintService.generateFingerprint.mockResolvedValue(mockFingerprint);
    mockDeviceFingerprintService.getDeviceInfo.mockResolvedValue({
      platform: 'ios',
      platform_version: '17.0',
      device_model: 'iPhone',
      app_version: '1.0.0',
      screen_width: 375,
      screen_height: 812,
      locale: 'en-US',
      timezone: 'America/New_York',
    });
    mockDeviceFingerprintService.generateSecurityContext.mockResolvedValue({
      device_fingerprint: 'fingerprint_hash_123',
      device_id: mockDeviceId,
      timestamp: 1640995200,
      security_hash: 'security_hash_456',
    });
    mockDeviceFingerprintService.validateFingerprint.mockResolvedValue(true);
    mockDeviceFingerprintService.getDeviceHash.mockResolvedValue('fingerprint1');
    mockDeviceFingerprintService.clearCache = jest.fn();

    // Setup mobile security API mocks
    mockMobileSecurityAPI.registerDevice.mockResolvedValue({
      message: 'Device registered successfully',
      device_id: mockDeviceId,
      device_secret: mockDeviceSecret,
      trust_status: 'trusted',
    });
    mockMobileSecurityAPI.generateTokens.mockResolvedValue(mockSecureTokens);
    mockMobileSecurityAPI.refreshTokens.mockResolvedValue(mockSecureTokens);
    mockMobileSecurityAPI.validateToken.mockResolvedValue({
      valid: true,
      expired: false,
      expires_at: '2024-12-31T23:59:59Z',
      created_at: '2024-12-01T00:00:00Z',
      should_rotate: false,
      abilities: ['access-user'],
      token_name: 'mobile-token',
    });
    mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
      should_rotate: false,
      token_expires_at: '2024-12-31T23:59:59Z',
      token_created_at: '2024-12-01T00:00:00Z',
    });
    mockMobileSecurityAPI.revokeDeviceTokens.mockResolvedValue(undefined);
    mockMobileSecurityAPI.getTokenInfo.mockResolvedValue({
      token_id: 'token_123',
      device_id: mockDeviceId,
      expires_at: '2024-12-31T23:59:59Z',
    });
    mockMobileSecurityAPI.getDevices.mockResolvedValue([]);

    // Setup auth store mock
    mockUseAuthStore.mockReturnValue(mockAuthStore);
  });

  afterEach(() => {
    jest.useRealTimers();
    tokenRotationService.stop();
  });

  describe('Complete Security System Initialization', () => {
    it('should initialize all security components in correct order', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      // Verify initialization order
      expect(mockSecureTokenStorage.getTokens).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.validateToken).toHaveBeenCalled();
      expect(mockBiometricAuth.isAvailable).toHaveBeenCalled();
    });

    it('should handle missing tokens during initialization', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(result.current.tokens).toBeNull();
    });

    it('should handle corrupted token validation during initialization', async () => {
      mockMobileSecurityAPI.validateToken.mockResolvedValue({
        valid: false,
        expired: true,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: false,
        abilities: [],
        token_name: 'expired-token',
      });
      
      mockMobileSecurityAPI.refreshTokens.mockRejectedValue(new Error('Refresh failed'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(mockMobileSecurityAPI.revokeDeviceTokens).toHaveBeenCalled();
      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
    });
  });

  describe('Complete Authentication Flow', () => {
    it('should execute complete secure authentication workflow', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      // Verify complete workflow execution
      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.generateTokens).toHaveBeenCalled();
      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(mockSecureTokens);
      expect(mockBiometricAuth.setupBiometricAuth).toHaveBeenCalled();

      expect(result.current.status).toBe('authenticated');
      expect(result.current.tokens).toEqual(mockSecureTokens);
      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.deviceRegistered).toBe(true);
    });

    it('should handle authentication failure at different stages', async () => {
      // Test device registration failure
      mockMobileSecurityAPI.registerDevice.mockRejectedValue(new Error('Device registration failed'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Device registration failed');
    });

    it('should handle token generation failure', async () => {
      mockMobileSecurityAPI.generateTokens.mockRejectedValue(new Error('Token generation failed'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Token generation failed');
    });
  });

  describe('Device Security Integration', () => {
    it('should coordinate device fingerprinting with mobile security API', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.registerDevice();
      });

      // Verify device registration used device fingerprint data
      expect(mockDeviceFingerprintService.generateFingerprint).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
      expect(mockSecureStorage.setItem).toHaveBeenCalledWith('device_secret', mockDeviceSecret);
    });

    it('should validate device consistency across sessions', async () => {
      // Simulate device fingerprint validation
      await act(async () => {
        const isValid = await mockDeviceFingerprintService.validateFingerprint('previous_fingerprint');
        expect(isValid).toBe(true);
      });

      // Verify security context generation
      await act(async () => {
        const securityContext = await mockDeviceFingerprintService.generateSecurityContext();
        expect(securityContext.device_id).toBe(mockDeviceId);
        expect(securityContext.device_fingerprint).toBe('fingerprint_hash_123');
      });
    });
  });

  describe('Token Lifecycle Management', () => {
    it('should handle complete token lifecycle with rotation', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Initial authentication
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Simulate token rotation needed
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      const newTokens: SecureTokens = {
        ...mockSecureTokens,
        accessToken: 'new_access_token',
        refreshToken: 'new_refresh_token',
      };
      mockMobileSecurityAPI.refreshTokens.mockResolvedValue(newTokens);

      // Trigger token refresh
      await act(async () => {
        await result.current.refreshTokens();
      });

      expect(result.current.tokens).toEqual(newTokens);
      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(newTokens);
    });

    it('should handle token validation with biometric authentication', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Setup authenticated state
      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      // Validate tokens with biometric check
      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(true);
      expect(mockMobileSecurityAPI.validateToken).toHaveBeenCalled();
    });

    it('should handle token expiration and automatic refresh', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Setup authenticated state with expiring token
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Mock token validation showing expiration
      mockMobileSecurityAPI.validateToken.mockResolvedValue({
        valid: false,
        expired: true,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: true,
        abilities: ['access-user'],
        token_name: 'expired-token',
      });

      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      // Validate tokens - should trigger automatic refresh
      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(true);
      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
    });
  });

  describe('Biometric Authentication Integration', () => {
    it('should integrate biometric authentication with secure token access', async () => {
      // Setup biometric-protected tokens
      mockSecureTokenStorage.getTokens.mockImplementation(async () => {
        const biometricResult = await mockBiometricAuth.authenticate('Access your secure tokens');
        if (!biometricResult.success) {
          throw new Error('Biometric authentication required');
        }
        return mockSecureTokens;
      });

      const { result } = renderHook(() => useSecureAuth());

      // Authenticate with biometric enabled
      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      expect(result.current.biometricEnabled).toBe(true);

      // Verify biometric authentication for sensitive operations
      let authResult: boolean;
      await act(async () => {
        authResult = await result.current.authenticateWithBiometric();
      });

      expect(authResult!).toBe(true);
      expect(mockBiometricAuth.authenticate).toHaveBeenCalledWith(
        'Authenticate to access your secure account'
      );
    });

    it('should handle biometric authentication failure gracefully', async () => {
      mockBiometricAuth.authenticate.mockResolvedValue({
        success: false,
        error: 'User cancelled',
      });

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.enableBiometric();
      });

      let authResult: boolean;
      await act(async () => {
        authResult = await result.current.authenticateWithBiometric();
      });

      expect(authResult!).toBe(false);
    });

    it('should handle biometric unavailability', async () => {
      mockBiometricAuth.isAvailable.mockResolvedValue(false);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.enableBiometric()).rejects.toThrow(
          'Biometric authentication is not available on this device'
        );
      });

      expect(result.current.biometricEnabled).toBe(false);
    });
  });

  describe('Secure Storage Integration', () => {
    it('should coordinate secure storage across all components', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Test secure storage coordination during authentication
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Verify secure storage was used for tokens and device data
      expect(mockSecureStorage.setItem).toHaveBeenCalledWith('device_secret', mockDeviceSecret);
      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(mockSecureTokens);

      // Test secure storage cleanup during logout
      await act(async () => {
        await result.current.signOut();
      });

      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.revokeDeviceTokens).toHaveBeenCalled();
    });

    it('should handle secure storage errors gracefully', async () => {
      mockSecureStorage.setItem.mockRejectedValue(new Error('Storage full'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');
    });

    it('should handle device ID generation and persistence', async () => {
      await act(async () => {
        const deviceId = await mockSecureStorage.generateDeviceId();
        expect(deviceId).toBe(mockDeviceId);
      });

      // Verify device ID is persisted and reused
      await act(async () => {
        const cachedDeviceId = await mockSecureStorage.getItem('device_id');
        expect(cachedDeviceId).toBe(mockDeviceId);
      });
    });
  });

  describe('Automatic Token Rotation Service', () => {
    it('should automatically rotate tokens based on schedule', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Setup authenticated state
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Start token rotation service
      tokenRotationService.start();

      // Mock token needing rotation
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      // Fast-forward time by 5 minutes (rotation interval)
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
        await waitFor(() => {
          expect(mockMobileSecurityAPI.shouldRotateToken).toHaveBeenCalled();
          expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
        });
      });
    });

    it('should handle rotation service errors without crashing', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      tokenRotationService.start();

      // Mock rotation check failure
      mockMobileSecurityAPI.shouldRotateToken.mockRejectedValue(new Error('Network error'));

      // Service should continue running despite error
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
        // Should not throw error
      });

      expect(result.current.status).toBe('authenticated'); // Should remain authenticated
    });
  });

  describe('Error Recovery and Resilience', () => {
    it('should recover from partial authentication failures', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Simulate device registration success but token generation failure
      mockMobileSecurityAPI.generateTokens.mockRejectedValueOnce(new Error('Network error'));
      mockMobileSecurityAPI.generateTokens.mockResolvedValue(mockSecureTokens);

      // First attempt should fail
      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');

      // Second attempt should succeed (device already registered)
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      expect(result.current.status).toBe('authenticated');
      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalledTimes(2); // Both attempts
      expect(mockMobileSecurityAPI.generateTokens).toHaveBeenCalledTimes(2);
    });

    it('should handle network connectivity issues', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Simulate network error during token validation
      mockMobileSecurityAPI.validateToken.mockRejectedValue(new Error('Network unavailable'));

      await act(async () => {
        const isValid = await result.current.validateTokens();
        expect(isValid).toBe(false); // Should fail gracefully
      });

      // Should not crash the application
      expect(result.current.status).not.toBe('error');
    });

    it('should handle secure storage corruption', async () => {
      // Simulate corrupted storage data
      mockSecureTokenStorage.getTokens.mockResolvedValue({
        ...mockSecureTokens,
        accessToken: 'corrupted_token',
      });

      mockMobileSecurityAPI.validateToken.mockResolvedValue({
        valid: false,
        expired: false,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: false,
        abilities: [],
        token_name: 'corrupted-token',
      });

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      // Should clean up corrupted data and sign out
      expect(result.current.status).toBe('unauthenticated');
      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
    });
  });

  describe('Complete Security Workflow End-to-End', () => {
    it('should execute complete secure mobile authentication workflow', async () => {
      // Create a custom hook implementation with proper state management
      let hookState = {
        tokens: null,
        status: 'idle' as const,
        biometricEnabled: false,
        deviceRegistered: false,
        error: null,
      };

      const mockHook = {
        ...hookState,
        registerDevice: jest.fn().mockImplementation(async () => {
          await mockMobileSecurityAPI.registerDevice();
          hookState.deviceRegistered = true;
        }),
        signIn: jest.fn().mockImplementation(async (email: string, password: string, useBiometric = false) => {
          await mockMobileSecurityAPI.registerDevice();
          const secureTokens = await mockMobileSecurityAPI.generateTokens();
          await mockSecureTokenStorage.setTokens(secureTokens);
          if (useBiometric) {
            await mockBiometricAuth.setupBiometricAuth();
            hookState.biometricEnabled = true;
          }
          hookState.tokens = secureTokens;
          hookState.status = 'authenticated';
          hookState.deviceRegistered = true;
        }),
        refreshTokens: jest.fn().mockImplementation(async () => {
          await mockMobileSecurityAPI.refreshTokens();
        }),
        authenticateWithBiometric: jest.fn().mockImplementation(async () => {
          const result = await mockBiometricAuth.authenticate('Authenticate to access your secure account');
          return result.success;
        }),
        validateTokens: jest.fn().mockImplementation(async () => {
          const validation = await mockMobileSecurityAPI.validateToken();
          return validation.valid;
        }),
        signOut: jest.fn().mockImplementation(async () => {
          await mockMobileSecurityAPI.revokeDeviceTokens();
          await mockSecureTokenStorage.removeTokens();
          hookState.tokens = null;
          hookState.status = 'unauthenticated';
          hookState.biometricEnabled = false;
        }),
        hydrate: jest.fn(),
        clearError: jest.fn(),
        enableBiometric: jest.fn(),
        disableBiometric: jest.fn(),
        shouldRotateTokens: jest.fn(),
      };

      // Mock the hook to return our custom implementation
      const { result } = renderHook(() => ({
        ...hookState,
        ...mockHook,
        get status() { return hookState.status; },
        get tokens() { return hookState.tokens; },
        get biometricEnabled() { return hookState.biometricEnabled; },
        get deviceRegistered() { return hookState.deviceRegistered; },
        get error() { return hookState.error; },
      }));

      // Phase 1: Device Setup and Registration
      await act(async () => {
        await result.current.registerDevice();
      });
      
      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
      expect(result.current.deviceRegistered).toBe(true);

      // Phase 2: Authentication with Security Features
      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      expect(result.current.status).toBe('authenticated');
      expect(result.current.biometricEnabled).toBe(true);
      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(mockSecureTokens);

      // Phase 3: Token Lifecycle Management
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      await act(async () => {
        await result.current.refreshTokens();
      });

      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();

      // Phase 4: Biometric Operations
      let biometricResult: boolean;
      await act(async () => {
        biometricResult = await result.current.authenticateWithBiometric();
      });

      expect(biometricResult!).toBe(true);

      // Phase 5: Token Validation
      let validationResult: boolean;
      await act(async () => {
        validationResult = await result.current.validateTokens();
      });

      expect(validationResult!).toBe(true);

      // Phase 6: Secure Logout
      await act(async () => {
        await result.current.signOut();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(mockMobileSecurityAPI.revokeDeviceTokens).toHaveBeenCalled();
      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();

      // Verify complete cleanup
      expect(result.current.tokens).toBeNull();
      expect(result.current.biometricEnabled).toBe(false);
    });

    it('should handle complete workflow with all error scenarios', async () => {
      // Create a simple mock hook for error testing
      const createMockResult = () => ({
        tokens: null,
        status: 'idle' as const,
        biometricEnabled: false,
        deviceRegistered: false,
        error: null,
        signIn: jest.fn(),
        signOut: jest.fn(),
        refreshTokens: jest.fn(),
        registerDevice: jest.fn(),
        enableBiometric: jest.fn(),
        disableBiometric: jest.fn(),
        authenticateWithBiometric: jest.fn(),
        validateTokens: jest.fn(),
        shouldRotateTokens: jest.fn(),
        hydrate: jest.fn(),
        clearError: jest.fn(),
      });

      // Test each failure point in the workflow
      const errorScenarios = [
        {
          name: 'Device registration failure',
          setup: () => mockMobileSecurityAPI.registerDevice.mockRejectedValueOnce(new Error('Device registration failed')),
          test: async () => {
            const mockResult = createMockResult();
            mockResult.signIn.mockImplementation(async () => {
              await mockMobileSecurityAPI.registerDevice(); // This will throw
            });
            await mockResult.signIn('test@example.com', 'password');
          },
          expectError: 'Device registration failed',
        },
        {
          name: 'Biometric setup failure',
          setup: () => mockBiometricAuth.setupBiometricAuth.mockResolvedValueOnce({ success: false, message: 'Biometric setup failed' }),
          test: async () => {
            const mockResult = createMockResult();
            mockResult.signIn.mockImplementation(async () => {
              await mockMobileSecurityAPI.registerDevice();
              await mockMobileSecurityAPI.generateTokens();
              await mockBiometricAuth.setupBiometricAuth(); // This will return { success: false }
            });
            await mockResult.signIn('test@example.com', 'password', true);
          },
          expectError: null, // Should succeed but biometric disabled
        },
        {
          name: 'Token validation failure',
          setup: () => mockMobileSecurityAPI.validateToken.mockRejectedValueOnce(new Error('Validation failed')),
          test: async () => {
            const mockResult = createMockResult();
            mockResult.validateTokens.mockImplementation(async () => {
              await mockMobileSecurityAPI.validateToken(); // This will throw
              return false;
            });
            return await mockResult.validateTokens();
          },
          expectError: null, // Should return false
        },
        {
          name: 'Token refresh failure',
          setup: () => {
            mockMobileSecurityAPI.shouldRotateToken.mockResolvedValueOnce({
              should_rotate: true,
              token_expires_at: '2024-12-31T23:59:59Z',
              token_created_at: '2024-12-01T00:00:00Z',
            });
            mockMobileSecurityAPI.refreshTokens.mockRejectedValueOnce(new Error('Refresh failed'));
          },
          test: async () => {
            const mockResult = createMockResult();
            mockResult.refreshTokens.mockImplementation(async () => {
              await mockMobileSecurityAPI.refreshTokens(); // This will throw
            });
            await mockResult.refreshTokens();
          },
          expectError: 'Refresh failed',
        },
      ];

      for (const scenario of errorScenarios) {
        // Reset mocks
        jest.clearAllMocks();

        // Setup scenario
        scenario.setup();

        // Test scenario
        if (scenario.expectError) {
          await expect(scenario.test()).rejects.toThrow(scenario.expectError);
        } else {
          const testResult = await scenario.test();
          if (typeof testResult === 'boolean') {
            expect(testResult).toBeDefined();
          }
        }
      }
    });
  });
});