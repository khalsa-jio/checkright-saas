import { create } from 'zustand';

import { mobileSecurityAPI } from '@/api/mobile-security';
import { biometricAuth } from '@/lib/biometric-auth';
import { type SecureTokens, secureTokenStorage } from '@/lib/secure-storage';

import { createSelectors } from '../utils';

/**
 * Secure Authentication System
 * Integrates biometric authentication, secure storage, and JWT token management
 */

export interface SecureAuthState {
  tokens: SecureTokens | null;
  status: 'idle' | 'loading' | 'authenticated' | 'unauthenticated' | 'error';
  biometricEnabled: boolean;
  deviceRegistered: boolean;
  error: string | null;
}

export interface SecureAuthActions {
  // Authentication
  signIn: (
    email: string,
    password: string,
    useBiometric?: boolean
  ) => Promise<void>;
  signOut: () => Promise<void>;
  refreshTokens: () => Promise<void>;

  // Device Management
  registerDevice: () => Promise<void>;

  // Biometric Authentication
  enableBiometric: () => Promise<void>;
  disableBiometric: () => Promise<void>;
  authenticateWithBiometric: () => Promise<boolean>;

  // Token Management
  validateTokens: () => Promise<boolean>;
  shouldRotateTokens: () => Promise<boolean>;

  // System
  hydrate: () => Promise<void>;
  clearError: () => void;
}

interface SecureAuthStore extends SecureAuthState, SecureAuthActions {}

const _useSecureAuth = create<SecureAuthStore>((set, get) => ({
  // State
  tokens: null,
  status: 'idle',
  biometricEnabled: false,
  deviceRegistered: false,
  error: null,

  // Actions
  signIn: async (email: string, password: string, useBiometric = false) => {
    try {
      set({ status: 'loading', error: null });

      // First, ensure device is registered
      await get().registerDevice();

      // TODO 1. Perform traditional login to get initial tokens
      // Note: You'll need to implement this based on your existing login API
      // For now, we'll assume you get tokens somehow and then secure them

      // Generate secure mobile tokens
      const secureTokens = await mobileSecurityAPI.generateTokens();

      // Store tokens securely
      await secureTokenStorage.setTokens(secureTokens);

      // Enable biometric if requested and available
      if (useBiometric) {
        const biometricResult = await biometricAuth.setupBiometricAuth();
        if (biometricResult.success) {
          set({ biometricEnabled: true });
        }
      }

      set({
        tokens: secureTokens,
        status: 'authenticated',
        deviceRegistered: true,
      });
    } catch (error: any) {
      console.error('SecureAuth: Sign in failed', error);
      set({
        status: 'error',
        error: error.message || 'Authentication failed',
        tokens: null,
      });
      throw error;
    }
  },

  signOut: async () => {
    try {
      set({ status: 'loading', error: null });

      // Revoke tokens on the server
      try {
        await mobileSecurityAPI.revokeDeviceTokens();
      } catch (error) {
        console.warn('Failed to revoke tokens on server:', error);
      }

      // Clear local tokens
      await secureTokenStorage.removeTokens();

      set({
        tokens: null,
        status: 'unauthenticated',
        biometricEnabled: false,
      });
    } catch (error: any) {
      console.error('SecureAuth: Sign out failed', error);
      set({ status: 'error', error: error.message || 'Sign out failed' });
    }
  },

  refreshTokens: async () => {
    try {
      const currentTokens = get().tokens;
      if (!currentTokens) {
        throw new Error('No tokens available for refresh');
      }

      // Check if we should rotate tokens
      const shouldRotate = await get().shouldRotateTokens();
      if (!shouldRotate) {
        return; // No need to refresh yet
      }

      const newTokens = await mobileSecurityAPI.refreshTokens();

      set({ tokens: newTokens });
    } catch (error: any) {
      console.error('SecureAuth: Token refresh failed', error);

      // If refresh fails, sign out user
      await get().signOut();
      throw error;
    }
  },

  registerDevice: async () => {
    try {
      if (get().deviceRegistered) {
        return; // Already registered
      }

      const result = await mobileSecurityAPI.registerDevice();

      set({ deviceRegistered: true });

      console.log('Device registered successfully:', result.message);
    } catch (error: any) {
      console.error('SecureAuth: Device registration failed', error);
      throw new Error('Device registration failed: ' + error.message);
    }
  },

  enableBiometric: async () => {
    try {
      const isAvailable = await biometricAuth.isAvailable();
      if (!isAvailable) {
        throw new Error(
          'Biometric authentication is not available on this device'
        );
      }

      const result = await biometricAuth.setupBiometricAuth();
      if (!result.success) {
        throw new Error(result.message);
      }

      set({ biometricEnabled: true });
    } catch (error: any) {
      console.error('SecureAuth: Enable biometric failed', error);
      set({ error: error.message });
      throw error;
    }
  },

  disableBiometric: async () => {
    try {
      set({ biometricEnabled: false });
      // Note: You might want to re-store tokens without biometric requirement
    } catch (error: any) {
      console.error('SecureAuth: Disable biometric failed', error);
      set({ error: error.message });
    }
  },

  authenticateWithBiometric: async (): Promise<boolean> => {
    try {
      if (!get().biometricEnabled) {
        return false;
      }

      const result = await biometricAuth.authenticate(
        'Authenticate to access your secure account'
      );

      return result.success;
    } catch (error: any) {
      console.error('SecureAuth: Biometric authentication failed', error);
      return false;
    }
  },

  validateTokens: async (): Promise<boolean> => {
    try {
      const tokens = get().tokens;
      if (!tokens) {
        return false;
      }

      const validation = await mobileSecurityAPI.validateToken();

      if (!validation.valid || validation.expired) {
        // Try to refresh tokens
        await get().refreshTokens();
        return true;
      }

      return true;
    } catch (error: any) {
      console.error('SecureAuth: Token validation failed', error);
      return false;
    }
  },

  shouldRotateTokens: async (): Promise<boolean> => {
    try {
      const rotationCheck = await mobileSecurityAPI.shouldRotateToken();
      return rotationCheck.should_rotate;
    } catch (error: any) {
      console.error('SecureAuth: Check token rotation failed', error);
      return false;
    }
  },

  hydrate: async () => {
    try {
      set({ status: 'loading' });

      // Check for stored tokens
      const storedTokens = await secureTokenStorage.getTokens();

      if (!storedTokens) {
        set({ status: 'unauthenticated' });
        return;
      }

      // Validate stored tokens
      const isValid = await get().validateTokens();

      if (isValid) {
        const currentTokens = await secureTokenStorage.getTokens();
        set({
          tokens: currentTokens,
          status: 'authenticated',
          deviceRegistered: true,
        });

        // Check if biometric is enabled
        const biometricAvailable = await biometricAuth.isAvailable();
        set({ biometricEnabled: biometricAvailable });
      } else {
        // Invalid tokens, sign out
        await get().signOut();
      }
    } catch (error: any) {
      console.error('SecureAuth: Hydration failed', error);
      set({
        status: 'error',
        error: error.message || 'Failed to initialize authentication',
      });
    }
  },

  clearError: () => {
    set({ error: null });
  },
}));

export const useSecureAuth = createSelectors(_useSecureAuth);

// Exported functions for external use
export const signOut = () => _useSecureAuth.getState().signOut();
export const signIn = (
  email: string,
  password: string,
  useBiometric?: boolean
) => _useSecureAuth.getState().signIn(email, password, useBiometric);
export const hydrateSecureAuth = () => _useSecureAuth.getState().hydrate();
export const refreshTokens = () => _useSecureAuth.getState().refreshTokens();

// Token auto-rotation service
class TokenRotationService {
  private rotationTimer: ReturnType<typeof setInterval> | null = null;

  start() {
    this.stop(); // Clear any existing timer

    // Check for token rotation every 5 minutes
    this.rotationTimer = setInterval(
      async () => {
        try {
          const authState = _useSecureAuth.getState();

          if (authState.status === 'authenticated' && authState.tokens) {
            const shouldRotate = await authState.shouldRotateTokens();

            if (shouldRotate) {
              await authState.refreshTokens();
              console.log('Tokens automatically rotated');
            }
          }
        } catch (error) {
          console.error('Auto token rotation failed:', error);
        }
      },
      5 * 60 * 1000
    ); // 5 minutes
  }

  stop() {
    if (this.rotationTimer) {
      clearInterval(this.rotationTimer);
      this.rotationTimer = null;
    }
  }
}

export const tokenRotationService = new TokenRotationService();
