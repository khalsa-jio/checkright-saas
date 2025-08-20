import { act, renderHook } from '@testing-library/react-native';

import { useAuthStore } from '../stores/authStore';

// Mock MMKV
jest.mock('react-native-mmkv', () => ({
  MMKV: jest.fn().mockImplementation(() => ({
    getString: jest.fn(),
    set: jest.fn(),
    delete: jest.fn(),
  })),
}));

// Mock AuthApi
jest.mock('../services/api', () => ({
  AuthApi: {
    acceptInvitation: jest.fn(),
    login: jest.fn(),
    logout: jest.fn(),
    getCurrentUser: jest.fn(),
    setupAuthInterceptor: jest.fn().mockReturnValue(() => {}),
  },
}));

describe('useAuthStore', () => {
  beforeEach(() => {
    // Reset store state before each test
    act(() => {
      useAuthStore.getState().clearAuth();
    });
  });

  it('should initialize with default state', () => {
    const { result } = renderHook(() => useAuthStore());

    expect(result.current.user).toBeNull();
    expect(result.current.token).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.isLoading).toBe(false);
    expect(result.current.rememberMe).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('should set user and update authentication state', () => {
    const { result } = renderHook(() => useAuthStore());
    const mockUser = {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'admin' as const,
      tenant_id: 'tenant1',
    };

    act(() => {
      result.current.setUser(mockUser);
    });

    expect(result.current.user).toEqual(mockUser);
    expect(result.current.isAuthenticated).toBe(true);
  });

  it('should set token and clear when null', () => {
    const { result } = renderHook(() => useAuthStore());

    act(() => {
      result.current.setToken('test-token');
    });

    expect(result.current.token).toBe('test-token');

    act(() => {
      result.current.setToken(null);
    });

    expect(result.current.token).toBeNull();
  });

  it('should manage loading state', () => {
    const { result } = renderHook(() => useAuthStore());

    act(() => {
      result.current.setLoading(true);
    });

    expect(result.current.isLoading).toBe(true);

    act(() => {
      result.current.setLoading(false);
    });

    expect(result.current.isLoading).toBe(false);
  });

  it('should manage error state', () => {
    const { result } = renderHook(() => useAuthStore());
    const errorMessage = 'Test error';

    act(() => {
      result.current.setError(errorMessage);
    });

    expect(result.current.error).toBe(errorMessage);

    act(() => {
      result.current.clearError();
    });

    expect(result.current.error).toBeNull();
  });

  it('should clear all auth state', () => {
    const { result } = renderHook(() => useAuthStore());
    const mockUser = {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'admin' as const,
      tenant_id: 'tenant1',
    };

    // Set some state first
    act(() => {
      result.current.setUser(mockUser);
      result.current.setToken('test-token');
      result.current.setRememberMe(true);
      result.current.setError('Some error');
    });

    // Clear auth
    act(() => {
      result.current.clearAuth();
    });

    expect(result.current.user).toBeNull();
    expect(result.current.token).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.rememberMe).toBe(false);
    expect(result.current.error).toBeNull();
  });
});
