import { useCurrentUser } from '../services/queries';
import { useAuthStore } from '../stores/authStore';

/**
 * Main auth hook that provides authentication state and actions
 */
export function useAuth() {
  const authStore = useAuthStore();

  // Use React Query for user data when authenticated
  const userQuery = useCurrentUser(!!authStore.token);

  return {
    // State
    user: authStore.user,
    token: authStore.token,
    isAuthenticated: authStore.isAuthenticated,
    isLoading: authStore.isLoading || userQuery.isLoading,
    rememberMe: authStore.rememberMe,
    tokenExpiresAt: authStore.tokenExpiresAt,
    error: authStore.error,

    // Computed state
    isTokenExpiring: authStore.checkTokenExpiration(),

    // Actions
    acceptInvitation: authStore.acceptInvitation,
    login: authStore.login,
    logout: authStore.logout,
    clearError: authStore.clearError,
    refreshToken: authStore.refreshToken,

    // Store management
    hydrate: authStore.hydrate,
  };
}
