import { MMKV } from 'react-native-mmkv';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import { AuthApi } from '../services/api';
import type { AuthMetadata, AuthResponse, User } from '../types';

// Regular storage for non-sensitive preferences
const regularStorage = new MMKV({
  id: 'auth-storage',
});

// Generate a unique encryption key per device installation
const getOrCreateEncryptionKey = (): string => {
  const ENCRYPTION_KEY_STORAGE_KEY = 'checkright-encryption-key';

  let encryptionKey = regularStorage.getString(ENCRYPTION_KEY_STORAGE_KEY);

  if (!encryptionKey) {
    // Generate a secure random key using React Native compatible method
    const characters =
      'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let result = '';

    // TODO 2. Use Math.random() with current timestamp for React Native compatibility
    // Note: For production, consider using expo-crypto or react-native-crypto for better security
    const randomSeed = Date.now() + Math.random();

    for (let i = 0; i < 64; i++) {
      const randomIndex =
        Math.floor((Math.random() + randomSeed) * characters.length) %
        characters.length;
      result += characters.charAt(randomIndex);
    }

    encryptionKey = result;
    regularStorage.set(ENCRYPTION_KEY_STORAGE_KEY, encryptionKey);
  }

  return encryptionKey;
};

// Secure storage for sensitive auth data
const secureStorage = new MMKV({
  id: 'auth-secure-storage',
  encryptionKey: getOrCreateEncryptionKey(),
});

export interface AuthState {
  // State
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  rememberMe: boolean;
  tokenExpiresAt: string | null;
  error: string | null;

  // Actions
  setUser: (user: User | null) => void;
  setToken: (token: string | null) => void;
  setLoading: (loading: boolean) => void;
  setRememberMe: (remember: boolean) => void;
  setTokenExpiresAt: (expiresAt: string | null) => void;
  setError: (error: string | null) => void;
  clearError: () => void;

  // Auth Actions
  acceptInvitation: (
    token: string,
    data: { name: string; password: string; password_confirmation: string }
  ) => Promise<void>;
  login: (
    email: string,
    password: string,
    rememberMe?: boolean
  ) => Promise<void>;
  logout: () => Promise<void>;
  loadStoredAuth: () => Promise<void>;
  clearAuth: () => void;
  refreshToken: () => Promise<void>;
  checkTokenExpiration: () => boolean;

  // Store management
  hydrate: () => void;
}

// Storage functions for secure data
const secureStorageApi = {
  getItem: (key: string): string | null => {
    const value = secureStorage.getString(key);
    return value || null;
  },
  setItem: (key: string, value: string): void => {
    secureStorage.set(key, value);
  },
  removeItem: (key: string): void => {
    secureStorage.delete(key);
  },
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      // Initial state
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
      rememberMe: false,
      tokenExpiresAt: null,
      error: null,

      // State setters
      setUser: (user) => set({ user, isAuthenticated: !!user }),
      setToken: (token) => {
        set({ token });

        // Store token securely
        if (token) {
          secureStorageApi.setItem('auth_token', token);
        } else {
          secureStorageApi.removeItem('auth_token');
        }
      },
      setLoading: (isLoading) => set({ isLoading }),
      setRememberMe: (rememberMe) => set({ rememberMe }),
      setTokenExpiresAt: (tokenExpiresAt) => set({ tokenExpiresAt }),
      setError: (error) => set({ error }),
      clearError: () => set({ error: null }),

      // Accept invitation and register user
      acceptInvitation: async (invitationToken, data) => {
        const state = get();
        state.setLoading(true);
        state.clearError();

        try {
          const authResponse: AuthResponse = await AuthApi.acceptInvitation(
            invitationToken,
            data
          );

          const { user, token, expires_at } = authResponse;

          // Store user and token
          state.setUser(user);
          state.setToken(token);
          state.setTokenExpiresAt(expires_at);

          // Store metadata securely
          const metadata: AuthMetadata = {
            rememberMe: false, // Registration doesn't set remember me
            expiresAt: expires_at,
            userId: user.id,
          };

          secureStorageApi.setItem('auth_metadata', JSON.stringify(metadata));
        } catch (error: any) {
          const errorMessage =
            error.response?.data?.message ||
            error.message ||
            'Registration failed. Please try again.';
          state.setError(errorMessage);

          // Log error for debugging (non-sensitive info only)
          console.warn('Registration failed:', {
            status: error.response?.status,
            message: errorMessage,
          });

          throw new Error(errorMessage);
        } finally {
          state.setLoading(false);
        }
      },

      // Login user
      login: async (email, password, rememberMe = false) => {
        const state = get();
        state.setLoading(true);
        state.clearError();

        try {
          const authResponse: AuthResponse = await AuthApi.login({
            email,
            password,
            remember_me: rememberMe,
          });

          const { user, token, expires_at, remember_me } = authResponse;

          // Update state
          state.setUser(user);
          state.setToken(token);
          state.setRememberMe(remember_me || false);
          state.setTokenExpiresAt(expires_at);

          // Store metadata securely
          const metadata: AuthMetadata = {
            rememberMe: remember_me || false,
            expiresAt: expires_at,
            userId: user.id,
          };

          secureStorageApi.setItem('auth_metadata', JSON.stringify(metadata));
        } catch (error: any) {
          const errorMessage =
            error.response?.data?.message ||
            error.message ||
            'Login failed. Please check your credentials.';
          state.setError(errorMessage);

          // Log error for debugging (non-sensitive info only)
          console.warn('Login failed:', {
            status: error.response?.status,
            message: errorMessage,
          });

          throw new Error(errorMessage);
        } finally {
          state.setLoading(false);
        }
      },

      // Logout user
      logout: async () => {
        const state = get();

        try {
          // Call logout API if we have a token
          if (state.token) {
            await AuthApi.logout();
          }
        } catch (error) {
          // Continue with logout even if API call fails
          console.warn('Logout API call failed:', error);
        } finally {
          // Clear state and storage regardless of API call result
          state.clearAuth();

          // Clear secure storage
          try {
            secureStorageApi.removeItem('auth_token');
            secureStorageApi.removeItem('auth_metadata');
          } catch (error) {
            console.warn('Failed to clear secure storage:', error);
          }
        }
      },

      // Load stored authentication data
      loadStoredAuth: async () => {
        const state = get();
        state.setLoading(true);

        try {
          // Load token from secure storage
          const token = secureStorageApi.getItem('auth_token');

          if (token) {
            // Load metadata
            const metadataString = secureStorageApi.getItem('auth_metadata');
            let metadata: AuthMetadata | null = null;

            if (metadataString) {
              try {
                metadata = JSON.parse(metadataString);
              } catch (error) {
                console.warn('Failed to parse auth metadata:', error);
              }
            }

            // Check if token is expired
            if (metadata?.expiresAt) {
              const expirationDate = new Date(metadata.expiresAt);
              const now = new Date();

              if (now >= expirationDate) {
                // Token expired, clear auth
                await state.logout();
                return;
              }
            }

            // Set token and fetch user data
            state.setToken(token);
            state.setRememberMe(metadata?.rememberMe || false);
            state.setTokenExpiresAt(metadata?.expiresAt || null);

            // Fetch current user data
            try {
              const user = await AuthApi.getCurrentUser();
              state.setUser(user);
            } catch (error) {
              // If user fetch fails, clear auth
              console.warn('Failed to fetch user data:', error);
              await state.logout();
            }
          }
        } catch (error) {
          console.warn('Failed to load stored auth:', error);
          // Clear any potentially corrupted data
          state.clearAuth();
        } finally {
          state.setLoading(false);
        }
      },

      // Clear authentication state
      clearAuth: () => {
        set({
          user: null,
          token: null,
          isAuthenticated: false,
          rememberMe: false,
          tokenExpiresAt: null,
          error: null,
        });
      },

      // Refresh token (placeholder for future implementation)
      refreshToken: async () => {
        const state = get();
        const { token } = state;

        if (!token) {
          await state.logout();
          return;
        }

        try {
          // Try to fetch user data to validate token
          const user = await AuthApi.getCurrentUser();
          state.setUser(user);
        } catch (_error) {
          // Token is invalid, logout
          await state.logout();
        }
      },

      // Check if token is expired
      checkTokenExpiration: () => {
        const { tokenExpiresAt } = get();

        if (!tokenExpiresAt) {
          return false; // No expiration data, assume valid
        }

        const expirationDate = new Date(tokenExpiresAt);
        const now = new Date();
        const fiveMinutes = 5 * 60 * 1000; // 5 minutes in milliseconds

        // Return true if token expires in less than 5 minutes
        return expirationDate.getTime() - now.getTime() < fiveMinutes;
      },

      // Hydrate store on app start
      hydrate: () => {
        const state = get();
        state.loadStoredAuth();
      },
    }),
    {
      name: 'auth-store',
      storage: createJSONStorage(() => ({
        getItem: (key: string) => {
          const value = regularStorage.getString(key);
          return value || null;
        },
        setItem: (key: string, value: string) => {
          regularStorage.set(key, value);
        },
        removeItem: (key: string) => {
          regularStorage.delete(key);
        },
      })),
      // Only persist non-sensitive data
      partialize: (state) => ({
        rememberMe: state.rememberMe,
        tokenExpiresAt: state.tokenExpiresAt,
      }),
    }
  )
);

// Setup auth interceptor when store is created
AuthApi.setupAuthInterceptor(
  () => useAuthStore.getState().token,
  () => useAuthStore.getState().logout()
);
