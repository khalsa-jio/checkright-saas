import { renderHook, act } from '@testing-library/react-native';

import { mobileSecurityAPI } from '@/api/mobile-security';
import { biometricAuth } from '@/lib/biometric-auth';
import { secureTokenStorage, SecureTokens } from '@/lib/secure-storage';

import {
  useSecureAuth,
  signIn,
  signOut,
  hydrateSecureAuth,
  refreshTokens,
  tokenRotationService,
  SecureAuthState,
  SecureAuthActions,
} from '../secure-auth';

// Mock dependencies
jest.mock('@/api/mobile-security');
jest.mock('@/lib/biometric-auth');
jest.mock('@/lib/secure-storage');

const mockMobileSecurityAPI = mobileSecurityAPI as jest.Mocked<typeof mobileSecurityAPI>;
const mockBiometricAuth = biometricAuth as jest.Mocked<typeof biometricAuth>;
const mockSecureTokenStorage = secureTokenStorage as jest.Mocked<typeof secureTokenStorage>;

describe('SecureAuth Zustand Store', () => {
  const mockTokens: SecureTokens = {
    accessToken: 'access_token_123',
    refreshToken: 'refresh_token_456',
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: 'device_abc123',
    tokenType: 'Bearer',
  };

  const mockNewTokens: SecureTokens = {
    accessToken: 'new_access_token_789',
    refreshToken: 'new_refresh_token_012',
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: 'device_abc123',
    tokenType: 'Bearer',
  };

  beforeEach(() => {
    jest.clearAllMocks();
    
    // Default mock implementations
    mockMobileSecurityAPI.registerDevice.mockResolvedValue({
      message: 'Device registered successfully',
      device_id: 'device_abc123',
      device_secret: 'secret_123',
      trust_status: 'trusted',
    });
    
    mockMobileSecurityAPI.generateTokens.mockResolvedValue(mockTokens);
    mockMobileSecurityAPI.refreshTokens.mockResolvedValue(mockNewTokens);
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

    mockBiometricAuth.isAvailable.mockResolvedValue(true);
    mockBiometricAuth.setupBiometricAuth.mockResolvedValue({
      success: true,
      message: 'Biometric auth enabled',
    });
    mockBiometricAuth.authenticate.mockResolvedValue({ success: true });

    mockSecureTokenStorage.getTokens.mockResolvedValue(mockTokens);
    mockSecureTokenStorage.setTokens.mockResolvedValue(undefined);
    mockSecureTokenStorage.removeTokens.mockResolvedValue(undefined);
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const { result } = renderHook(() => useSecureAuth());

      expect(result.current.tokens).toBeNull();
      expect(result.current.status).toBe('idle');
      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.deviceRegistered).toBe(false);
      expect(result.current.error).toBeNull();
    });
  });

  describe('signIn', () => {
    it('should sign in successfully without biometric', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      expect(result.current.status).toBe('authenticated');
      expect(result.current.tokens).toEqual(mockTokens);
      expect(result.current.deviceRegistered).toBe(true);
      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.error).toBeNull();

      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.generateTokens).toHaveBeenCalled();
      expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(mockTokens);
    });

    it('should sign in successfully with biometric enabled', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      expect(result.current.status).toBe('authenticated');
      expect(result.current.biometricEnabled).toBe(true);
      expect(mockBiometricAuth.setupBiometricAuth).toHaveBeenCalled();
    });

    it('should handle biometric setup failure gracefully', async () => {
      mockBiometricAuth.setupBiometricAuth.mockResolvedValue({
        success: false,
        message: 'Biometric setup failed',
      });

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signIn('test@example.com', 'password', true);
      });

      expect(result.current.status).toBe('authenticated');
      expect(result.current.biometricEnabled).toBe(false); // Should remain false on failure
    });

    it('should handle sign in errors', async () => {
      const error = new Error('Device registration failed');
      mockMobileSecurityAPI.registerDevice.mockRejectedValue(error);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Device registration failed');
      expect(result.current.tokens).toBeNull();
    });

    it('should handle token generation failure', async () => {
      const error = new Error('Token generation failed');
      mockMobileSecurityAPI.generateTokens.mockRejectedValue(error);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Token generation failed');
    });
  });

  describe('signOut', () => {
    it('should sign out successfully', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // First sign in
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Then sign out
      await act(async () => {
        await result.current.signOut();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(result.current.tokens).toBeNull();
      expect(result.current.biometricEnabled).toBe(false);
      
      expect(mockMobileSecurityAPI.revokeDeviceTokens).toHaveBeenCalled();
      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
    });

    it('should handle server token revocation failure gracefully', async () => {
      mockMobileSecurityAPI.revokeDeviceTokens.mockRejectedValue(new Error('Server error'));
      
      const { result } = renderHook(() => useSecureAuth());

      // Sign in first
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Sign out should still succeed despite server error
      await act(async () => {
        await result.current.signOut();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(mockSecureTokenStorage.removeTokens).toHaveBeenCalled();
    });

    it('should handle local token cleanup failure', async () => {
      mockSecureTokenStorage.removeTokens.mockRejectedValue(new Error('Storage error'));
      
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.signOut();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Storage error');
    });
  });

  describe('refreshTokens', () => {
    it('should refresh tokens when rotation is needed', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      const { result } = renderHook(() => useSecureAuth());

      // Set initial tokens
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Refresh tokens
      await act(async () => {
        await result.current.refreshTokens();
      });

      expect(result.current.tokens).toEqual(mockNewTokens);
      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
    });

    it('should not refresh tokens when rotation is not needed', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: false,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      const { result } = renderHook(() => useSecureAuth());

      // Set initial tokens
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Try to refresh tokens
      await act(async () => {
        await result.current.refreshTokens();
      });

      expect(result.current.tokens).toEqual(mockTokens); // Should remain original tokens
      expect(mockMobileSecurityAPI.refreshTokens).not.toHaveBeenCalled();
    });

    it('should handle refresh failure and sign out user', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });
      mockMobileSecurityAPI.refreshTokens.mockRejectedValue(new Error('Refresh failed'));

      const { result } = renderHook(() => useSecureAuth());

      // Set initial tokens
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      // Refresh should fail and sign out
      await act(async () => {
        await expect(result.current.refreshTokens()).rejects.toThrow('Refresh failed');
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(result.current.tokens).toBeNull();
    });

    it('should handle missing tokens', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.refreshTokens()).rejects.toThrow('No tokens available for refresh');
      });
    });
  });

  describe('registerDevice', () => {
    it('should register device successfully', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.registerDevice();
      });

      expect(result.current.deviceRegistered).toBe(true);
      expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
    });

    it('should not register device if already registered', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // First registration
      await act(async () => {
        await result.current.registerDevice();
      });

      jest.clearAllMocks();

      // Second registration should skip
      await act(async () => {
        await result.current.registerDevice();
      });

      expect(mockMobileSecurityAPI.registerDevice).not.toHaveBeenCalled();
    });

    it('should handle device registration failure', async () => {
      const error = new Error('Registration failed');
      mockMobileSecurityAPI.registerDevice.mockRejectedValue(error);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.registerDevice()).rejects.toThrow('Device registration failed: Registration failed');
      });

      expect(result.current.deviceRegistered).toBe(false);
    });
  });

  describe('Biometric Authentication', () => {
    it('should enable biometric authentication', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.enableBiometric();
      });

      expect(result.current.biometricEnabled).toBe(true);
      expect(mockBiometricAuth.isAvailable).toHaveBeenCalled();
      expect(mockBiometricAuth.setupBiometricAuth).toHaveBeenCalled();
    });

    it('should handle biometric not available', async () => {
      mockBiometricAuth.isAvailable.mockResolvedValue(false);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.enableBiometric()).rejects.toThrow(
          'Biometric authentication is not available on this device'
        );
      });

      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.error).toBe('Biometric authentication is not available on this device');
    });

    it('should handle biometric setup failure', async () => {
      mockBiometricAuth.setupBiometricAuth.mockResolvedValue({
        success: false,
        message: 'Setup failed',
      });

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await expect(result.current.enableBiometric()).rejects.toThrow('Setup failed');
      });

      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.error).toBe('Setup failed');
    });

    it('should disable biometric authentication', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // First enable biometric
      await act(async () => {
        await result.current.enableBiometric();
      });

      expect(result.current.biometricEnabled).toBe(true);

      // Then disable it
      await act(async () => {
        await result.current.disableBiometric();
      });

      expect(result.current.biometricEnabled).toBe(false);
    });

    it('should authenticate with biometric', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Enable biometric first
      await act(async () => {
        await result.current.enableBiometric();
      });

      let authResult: boolean;
      await act(async () => {
        authResult = await result.current.authenticateWithBiometric();
      });

      expect(authResult!).toBe(true);
      expect(mockBiometricAuth.authenticate).toHaveBeenCalledWith(
        'Authenticate to access your secure account'
      );
    });

    it('should fail biometric authentication when not enabled', async () => {
      const { result } = renderHook(() => useSecureAuth());

      let authResult: boolean;
      await act(async () => {
        authResult = await result.current.authenticateWithBiometric();
      });

      expect(authResult!).toBe(false);
      expect(mockBiometricAuth.authenticate).not.toHaveBeenCalled();
    });

    it('should handle biometric authentication failure', async () => {
      mockBiometricAuth.authenticate.mockResolvedValue({
        success: false,
        error: 'User cancelled',
      });

      const { result } = renderHook(() => useSecureAuth());

      // Enable biometric first
      await act(async () => {
        await result.current.enableBiometric();
      });

      let authResult: boolean;
      await act(async () => {
        authResult = await result.current.authenticateWithBiometric();
      });

      expect(authResult!).toBe(false);
    });
  });

  describe('Token Validation', () => {
    it('should validate tokens successfully', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Set tokens first
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(true);
      expect(mockMobileSecurityAPI.validateToken).toHaveBeenCalled();
    });

    it('should return false when no tokens available', async () => {
      const { result } = renderHook(() => useSecureAuth());

      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(false);
      expect(mockMobileSecurityAPI.validateToken).not.toHaveBeenCalled();
    });

    it('should refresh tokens when validation shows expiration', async () => {
      mockMobileSecurityAPI.validateToken.mockResolvedValue({
        valid: false,
        expired: true,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: true,
        abilities: ['access-user'],
        token_name: 'mobile-token',
      });

      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      const { result } = renderHook(() => useSecureAuth());

      // Set tokens first
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(true);
      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
    });

    it('should handle validation errors', async () => {
      mockMobileSecurityAPI.validateToken.mockRejectedValue(new Error('Validation failed'));

      const { result } = renderHook(() => useSecureAuth());

      // Set tokens first
      await act(async () => {
        await result.current.signIn('test@example.com', 'password');
      });

      let isValid: boolean;
      await act(async () => {
        isValid = await result.current.validateTokens();
      });

      expect(isValid!).toBe(false);
    });
  });

  describe('shouldRotateTokens', () => {
    it('should return rotation status correctly', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      const { result } = renderHook(() => useSecureAuth());

      let shouldRotate: boolean;
      await act(async () => {
        shouldRotate = await result.current.shouldRotateTokens();
      });

      expect(shouldRotate!).toBe(true);
      expect(mockMobileSecurityAPI.shouldRotateToken).toHaveBeenCalled();
    });

    it('should handle rotation check errors', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockRejectedValue(new Error('Check failed'));

      const { result } = renderHook(() => useSecureAuth());

      let shouldRotate: boolean;
      await act(async () => {
        shouldRotate = await result.current.shouldRotateTokens();
      });

      expect(shouldRotate!).toBe(false);
    });
  });

  describe('hydrate', () => {
    it('should hydrate with valid stored tokens', async () => {
      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('authenticated');
      expect(result.current.tokens).toEqual(mockTokens);
      expect(result.current.deviceRegistered).toBe(true);
      expect(result.current.biometricEnabled).toBe(true); // isAvailable returns true
    });

    it('should handle no stored tokens', async () => {
      mockSecureTokenStorage.getTokens.mockResolvedValue(null);

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(result.current.tokens).toBeNull();
    });

    it('should sign out on invalid stored tokens', async () => {
      mockMobileSecurityAPI.validateToken.mockResolvedValue({
        valid: false,
        expired: true,
        expires_at: '2024-12-31T23:59:59Z',
        created_at: '2024-12-01T00:00:00Z',
        should_rotate: false,
        abilities: [],
        token_name: 'expired-token',
      });

      // Mock refresh to fail
      mockMobileSecurityAPI.refreshTokens.mockRejectedValue(new Error('Refresh failed'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('unauthenticated');
      expect(result.current.tokens).toBeNull();
    });

    it('should handle hydration errors', async () => {
      mockSecureTokenStorage.getTokens.mockRejectedValue(new Error('Storage error'));

      const { result } = renderHook(() => useSecureAuth());

      await act(async () => {
        await result.current.hydrate();
      });

      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Storage error');
    });
  });

  describe('clearError', () => {
    it('should clear error state', async () => {
      const { result } = renderHook(() => useSecureAuth());

      // Set an error state
      mockMobileSecurityAPI.registerDevice.mockRejectedValue(new Error('Test error'));
      
      await act(async () => {
        await expect(result.current.signIn('test@example.com', 'password')).rejects.toThrow();
      });

      expect(result.current.error).toBe('Test error');

      // Clear the error
      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
    });
  });

  describe('Exported Functions', () => {
    beforeEach(() => {
      // Reset any existing state
      act(() => {
        const store = useSecureAuth as any;
        store.setState({
          tokens: null,
          status: 'idle',
          biometricEnabled: false,
          deviceRegistered: false,
          error: null,
        });
      });
    });

    it('should export signIn function', async () => {
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      const { result } = renderHook(() => useSecureAuth());
      expect(result.current.status).toBe('authenticated');
    });

    it('should export signOut function', async () => {
      // Sign in first
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      // Then sign out
      await act(async () => {
        await signOut();
      });

      const { result } = renderHook(() => useSecureAuth());
      expect(result.current.status).toBe('unauthenticated');
    });

    it('should export hydrateSecureAuth function', async () => {
      await act(async () => {
        await hydrateSecureAuth();
      });

      const { result } = renderHook(() => useSecureAuth());
      expect(result.current.status).toBe('authenticated');
    });

    it('should export refreshTokens function', async () => {
      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      // Set initial state
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      // Use exported refresh function
      await act(async () => {
        await refreshTokens();
      });

      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
    });
  });

  describe('TokenRotationService', () => {
    beforeEach(() => {
      jest.useFakeTimers();
      tokenRotationService.stop(); // Ensure clean state
    });

    afterEach(() => {
      jest.runOnlyPendingTimers();
      jest.useRealTimers();
      tokenRotationService.stop();
    });

    it('should start token rotation service', () => {
      tokenRotationService.start();

      // Verify timer was set
      expect(setInterval).toHaveBeenCalledWith(
        expect.any(Function),
        5 * 60 * 1000 // 5 minutes
      );
    });

    it('should stop token rotation service', () => {
      tokenRotationService.start();
      tokenRotationService.stop();

      expect(clearInterval).toHaveBeenCalled();
    });

    it('should automatically rotate tokens when needed', async () => {
      // Set up authenticated state
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: true,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      tokenRotationService.start();

      // Fast-forward time by 5 minutes
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
      });

      expect(mockMobileSecurityAPI.shouldRotateToken).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.refreshTokens).toHaveBeenCalled();
    });

    it('should not rotate tokens when not needed', async () => {
      // Set up authenticated state
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      mockMobileSecurityAPI.shouldRotateToken.mockResolvedValue({
        should_rotate: false,
        token_expires_at: '2024-12-31T23:59:59Z',
        token_created_at: '2024-12-01T00:00:00Z',
      });

      tokenRotationService.start();

      // Fast-forward time by 5 minutes
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
      });

      expect(mockMobileSecurityAPI.shouldRotateToken).toHaveBeenCalled();
      expect(mockMobileSecurityAPI.refreshTokens).not.toHaveBeenCalled();
    });

    it('should handle rotation errors gracefully', async () => {
      // Set up authenticated state
      await act(async () => {
        await signIn('test@example.com', 'password');
      });

      mockMobileSecurityAPI.shouldRotateToken.mockRejectedValue(new Error('Rotation check failed'));

      tokenRotationService.start();

      // Should not throw when rotation check fails
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
      });

      // Service should continue running despite error
      expect(clearInterval).not.toHaveBeenCalled();
    });

    it('should not attempt rotation when unauthenticated', async () => {
      tokenRotationService.start();

      // Fast-forward time by 5 minutes
      await act(async () => {
        jest.advanceTimersByTime(5 * 60 * 1000);
      });

      expect(mockMobileSecurityAPI.shouldRotateToken).not.toHaveBeenCalled();
    });
  });

  describe('Selectors', () => {
    it('should provide individual selectors via createSelectors', () => {
      const { result } = renderHook(() => useSecureAuth());

      // Test individual selectors exist (these are created by createSelectors utility)
      expect(typeof result.current.signIn).toBe('function');
      expect(typeof result.current.signOut).toBe('function');
      expect(typeof result.current.hydrate).toBe('function');
      expect(typeof result.current.clearError).toBe('function');
    });
  });
});