import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import type {
  AuthResponse,
  InvitationAcceptance,
  LoginCredentials,
} from '../types';
import { AuthApi } from './api';

// Query keys for React Query
export const authQueryKeys = {
  user: ['auth', 'user'] as const,
  auth: ['auth'] as const,
};

/**
 * Hook for accepting invitation
 */
export function useAcceptInvitation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      token,
      data,
    }: {
      token: string;
      data: InvitationAcceptance;
    }) => AuthApi.acceptInvitation(token, data),
    onSuccess: (authResponse: AuthResponse) => {
      // Cache the user data
      queryClient.setQueryData(authQueryKeys.user, authResponse.user);
    },
    onError: (error) => {
      console.error('Accept invitation failed:', error);
    },
  });
}

/**
 * Hook for user login
 */
export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (credentials: LoginCredentials) => AuthApi.login(credentials),
    onSuccess: (authResponse: AuthResponse) => {
      // Cache the user data
      queryClient.setQueryData(authQueryKeys.user, authResponse.user);
    },
    onError: (error) => {
      console.error('Login failed:', error);
    },
  });
}

/**
 * Hook for user logout
 */
export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => AuthApi.logout(),
    onSuccess: () => {
      // Clear all auth-related cache
      queryClient.removeQueries({ queryKey: authQueryKeys.auth });
      queryClient.clear();
    },
    onError: (error) => {
      console.warn('Logout API call failed:', error);
      // Clear cache anyway on logout failure
      queryClient.removeQueries({ queryKey: authQueryKeys.auth });
    },
  });
}

/**
 * Hook for getting current user
 * Only fetches if we have a token
 */
export function useCurrentUser(enabled: boolean = true) {
  return useQuery({
    queryKey: authQueryKeys.user,
    queryFn: () => AuthApi.getCurrentUser(),
    enabled,
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: (failureCount, error: any) => {
      // Don't retry on 401 errors
      if (error?.response?.status === 401) {
        return false;
      }
      return failureCount < 3;
    },
  });
}

/**
 * Hook for refreshing token
 */
export function useRefreshToken() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => AuthApi.refreshToken(),
    onSuccess: (authResponse: AuthResponse) => {
      // Update cached user data
      queryClient.setQueryData(authQueryKeys.user, authResponse.user);
    },
    onError: (error) => {
      console.error('Token refresh failed:', error);
      // Clear auth cache on refresh failure
      queryClient.removeQueries({ queryKey: authQueryKeys.auth });
    },
  });
}
