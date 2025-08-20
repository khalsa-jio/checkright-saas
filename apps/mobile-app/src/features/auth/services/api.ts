import { client } from '@/api/common/client';

import type {
  AuthResponse,
  InvitationAcceptance,
  LoginCredentials,
  User,
  OAuthInitResponse,
  OAuthCallbackData,
} from '../types';

/**
 * Authentication API service
 * Handles all auth-related API calls using the existing axios client
 */
export class AuthApi {
  /**
   * Accept invitation and register user
   */
  static async acceptInvitation(
    token: string,
    data: InvitationAcceptance
  ): Promise<AuthResponse> {
    const response = await client.post(`/invitations/${token}/accept`, data);
    return response.data;
  }

  /**
   * Login user with credentials
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await client.post('/auth/login', credentials);
    return response.data;
  }

  /**
   * Initialize OAuth flow for mobile app
   */
  static async initializeOAuth(
    provider: 'google' | 'facebook' | 'instagram',
    options?: {
      tenantId?: string;
      deviceId?: string;
      appVersion?: string;
    }
  ): Promise<OAuthInitResponse> {
    const response = await client.post(`/mobile/oauth/${provider}/initialize`, {
      tenant_id: options?.tenantId,
      device_id: options?.deviceId,
      app_version: options?.appVersion,
    });
    return response.data;
  }

  /**
   * Complete OAuth flow with authorization code
   */
  static async completeOAuth(
    provider: 'google' | 'facebook' | 'instagram',
    data: OAuthCallbackData
  ): Promise<AuthResponse> {
    const response = await client.post(`/mobile/oauth/${provider}/callback`, data);
    return response.data;
  }

  /**
   * Logout user (revoke token)
   */
  static async logout(): Promise<void> {
    await client.post('/auth/logout');
  }

  /**
   * Get current user data
   */
  static async getCurrentUser(): Promise<User> {
    const response = await client.get('/user');
    return response.data;
  }

  /**
   * Refresh user token (if refresh endpoint exists)
   */
  static async refreshToken(): Promise<AuthResponse> {
    const response = await client.post('/auth/refresh');
    return response.data;
  }

  /**
   * Setup auth interceptor for automatic token handling
   */
  static setupAuthInterceptor(
    getToken: () => string | null,
    onUnauthorized: () => void
  ): () => void {
    // Request interceptor to add authorization header
    const requestInterceptor = client.interceptors.request.use(
      (config) => {
        const token = getToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor to handle unauthorized responses
    const responseInterceptor = client.interceptors.response.use(
      (response) => response,
      async (error) => {
        if (error.response?.status === 401) {
          // Unauthorized, likely token expired
          onUnauthorized();
        }
        return Promise.reject(error);
      }
    );

    // Return cleanup function
    return () => {
      client.interceptors.request.eject(requestInterceptor);
      client.interceptors.response.eject(responseInterceptor);
    };
  }
}
