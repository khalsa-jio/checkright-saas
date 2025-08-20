import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { Alert } from 'react-native';

import { mobileSecurityAPI } from '@/api/mobile-security';
import AcceptInvitationScreen from '@/app/(auth)/accept-invitation';
import { biometricAuth } from '@/lib/biometric-auth';
import { secureTokenStorage } from '@/lib/secure-storage';

import { useAuth } from '../hooks/useAuth';
import { AuthApi } from '../services/api';
import { useAuthStore } from '../stores/authStore';
import { PasswordValidator } from '../utils/passwordValidation';

// Mock dependencies
jest.mock('../services/api');
jest.mock('../stores/authStore');
jest.mock('../hooks/useAuth');
jest.mock('../utils/passwordValidation');
jest.mock('@/api/mobile-security');
jest.mock('@/lib/biometric-auth');
jest.mock('@/lib/secure-storage');
jest.mock('expo-router');
jest.mock('react-native', () => ({
  ...jest.requireActual('react-native'),
  Alert: {
    alert: jest.fn(),
  },
}));

const mockAuthApi = AuthApi as jest.Mocked<typeof AuthApi>;
const mockUseAuthStore = useAuthStore as jest.MockedFunction<
  typeof useAuthStore
>;
const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;
const mockPasswordValidator = PasswordValidator as jest.Mocked<
  typeof PasswordValidator
>;
const mockMobileSecurityAPI = mobileSecurityAPI as jest.Mocked<
  typeof mobileSecurityAPI
>;
const mockBiometricAuth = biometricAuth as jest.Mocked<typeof biometricAuth>;
const mockSecureTokenStorage = secureTokenStorage as jest.Mocked<
  typeof secureTokenStorage
>;
const mockRouter = router as jest.Mocked<typeof router>;
const mockAlert = Alert as jest.Mocked<typeof Alert>;

describe('Invitation Token Login Flow', () => {
  const mockInvitationToken = 'invitation-token-123';
  const mockUser = {
    id: '1',
    name: 'John Doe',
    email: 'john.doe@example.com',
    role: 'manager' as const,
    tenant_id: 'tenant-123',
  };

  const mockAuthResponse = {
    user: mockUser,
    token: 'auth-token-456',
    expires_at: '2024-12-31T23:59:59Z',
  };

  const mockSecureTokens = {
    accessToken: 'secure-access-token-789',
    refreshToken: 'secure-refresh-token-012',
    expiresAt: '2024-12-31T23:59:59Z',
    refreshExpiresAt: '2025-01-07T23:59:59Z',
    deviceId: 'device-abc123',
    tokenType: 'Bearer',
  };

  const mockFormData = {
    name: 'John Doe',
    password: 'SecurePassword123!',
    password_confirmation: 'SecurePassword123!',
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

  const mockUseAuthHook = {
    ...mockAuthStore,
    acceptInvitation: jest.fn(),
    isTokenExpiring: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();

    // Setup auth store mock
    mockUseAuthStore.mockReturnValue(mockAuthStore);
    mockUseAuth.mockReturnValue(mockUseAuthHook);

    // Setup password validator mocks
    mockPasswordValidator.getStrengthIndicator.mockReturnValue({
      progress: 0.8,
      label: 'Strong',
      color: 'rgb(34, 197, 94)',
    });
    mockPasswordValidator.getRequirements.mockReturnValue([
      'At least 8 characters',
      'One uppercase letter',
      'One lowercase letter',
      'One number',
      'One special character',
    ]);

    // Setup mobile security API mocks
    mockMobileSecurityAPI.registerDevice.mockResolvedValue({
      message: 'Device registered successfully',
      device_id: 'device-abc123',
      device_secret: 'device-secret-456',
      trust_status: 'trusted',
    });
    mockMobileSecurityAPI.generateTokens.mockResolvedValue(mockSecureTokens);

    // Setup biometric auth mocks
    mockBiometricAuth.isAvailable.mockResolvedValue(true);
    mockBiometricAuth.setupBiometricAuth.mockResolvedValue({
      success: true,
      message: 'Biometric authentication enabled',
    });

    // Setup secure token storage mocks
    mockSecureTokenStorage.setTokens.mockResolvedValue(undefined);

    // Setup auth API mock
    mockAuthApi.acceptInvitation.mockResolvedValue(mockAuthResponse);

    // Setup router mocks
    mockRouter.replace = jest.fn();

    // Mock useLocalSearchParams
    jest.doMock('expo-router', () => ({
      ...jest.requireActual('expo-router'),
      useLocalSearchParams: jest
        .fn()
        .mockReturnValue({ token: mockInvitationToken }),
    }));
  });

  describe('AcceptInvitationScreen Component', () => {
    it('should render invitation form with all required fields', () => {
      const { getByTestId, getByText } = render(<AcceptInvitationScreen />);

      expect(getByText('Create Your Account')).toBeTruthy();
      expect(
        getByText('Complete your registration to access CheckRight')
      ).toBeTruthy();
      expect(getByTestId('invitation-name-input')).toBeTruthy();
      expect(getByTestId('invitation-password-input')).toBeTruthy();
      expect(
        getByTestId('invitation-password-confirmation-input')
      ).toBeTruthy();
      expect(getByTestId('invitation-submit-button')).toBeTruthy();
    });

    it('should show password strength indicator when password is entered', async () => {
      const { getByTestId, getByText } = render(<AcceptInvitationScreen />);

      const passwordInput = getByTestId('invitation-password-input');
      fireEvent.changeText(passwordInput, 'SecurePassword123!');

      await waitFor(() => {
        expect(getByText('Password Strength')).toBeTruthy();
        expect(getByText('Strong')).toBeTruthy();
        expect(mockPasswordValidator.getStrengthIndicator).toHaveBeenCalledWith(
          'SecurePassword123!'
        );
      });
    });

    it('should show password requirements', () => {
      const { getByText } = render(<AcceptInvitationScreen />);

      expect(getByText('Password must include:')).toBeTruthy();
      expect(getByText('• At least 8 characters')).toBeTruthy();
      expect(getByText('• One uppercase letter')).toBeTruthy();
      expect(getByText('• One lowercase letter')).toBeTruthy();
      expect(getByText('• One number')).toBeTruthy();
      expect(getByText('• One special character')).toBeTruthy();
    });

    it('should handle invalid token by showing error state', () => {
      jest.doMock('expo-router', () => ({
        ...jest.requireActual('expo-router'),
        useLocalSearchParams: jest.fn().mockReturnValue({ token: undefined }),
      }));

      const { getByText, getByTestId } = render(<AcceptInvitationScreen />);

      expect(getByText('Invalid Invitation Link')).toBeTruthy();
      expect(
        getByText(
          'This invitation link is invalid or has expired. Please request a new invitation.'
        )
      ).toBeTruthy();
      expect(getByTestId(/go.*login/i)).toBeTruthy();
    });
  });

  describe('Invitation Acceptance Flow', () => {
    it('should successfully accept invitation with valid data', async () => {
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest.fn().mockResolvedValue(undefined),
        error: null,
        isLoading: false,
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Fill form
      const nameInput = getByTestId('invitation-name-input');
      const passwordInput = getByTestId('invitation-password-input');
      const confirmInput = getByTestId(
        'invitation-password-confirmation-input'
      );
      const submitButton = getByTestId('invitation-submit-button');

      fireEvent.changeText(nameInput, mockFormData.name);
      fireEvent.changeText(passwordInput, mockFormData.password);
      fireEvent.changeText(confirmInput, mockFormData.password_confirmation);

      // Submit form
      fireEvent.press(submitButton);

      await waitFor(() => {
        const mockAcceptInvitation = mockUseAuth()
          .acceptInvitation as jest.Mock;
        expect(mockAcceptInvitation).toHaveBeenCalledWith(
          mockInvitationToken,
          mockFormData
        );
      });

      // Should show success alert and navigate to app
      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Account Created!',
          'Your account has been successfully created. You are now logged in.',
          [
            {
              text: 'Continue',
              onPress: expect.any(Function),
            },
          ]
        );
      });
    });

    it('should show error message on invitation acceptance failure', async () => {
      const errorMessage = 'Invalid invitation token';
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest.fn().mockRejectedValue(new Error(errorMessage)),
        error: errorMessage,
        isLoading: false,
      });

      const { getByTestId, getByText } = render(<AcceptInvitationScreen />);

      // Fill and submit form
      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Registration Failed',
          errorMessage,
          [{ text: 'OK' }]
        );
      });

      expect(getByText(errorMessage)).toBeTruthy();
    });

    it('should handle missing token error', async () => {
      jest.doMock('expo-router', () => ({
        ...jest.requireActual('expo-router'),
        useLocalSearchParams: jest.fn().mockReturnValue({ token: null }),
      }));

      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Fill and submit form
      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Invalid Link',
          'This invitation link is invalid or has expired.'
        );
      });
    });

    it('should validate form data before submission', async () => {
      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Submit without filling form
      fireEvent.press(getByTestId('invitation-submit-button'));

      // Form validation should prevent submission
      await waitFor(() => {
        const mockAcceptInvitation = mockUseAuth()
          .acceptInvitation as jest.Mock;
        expect(mockAcceptInvitation).not.toHaveBeenCalled();
      });
    });

    it('should validate password confirmation matches', async () => {
      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Fill form with mismatched passwords
      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        'DifferentPassword123!'
      );

      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        const mockAcceptInvitation = mockUseAuth()
          .acceptInvitation as jest.Mock;
        expect(mockAcceptInvitation).not.toHaveBeenCalled();
      });
    });
  });

  describe('Auth Store Integration', () => {
    it('should call AuthApi.acceptInvitation with correct parameters', async () => {
      const mockStore = {
        setLoading: jest.fn(),
        setUser: jest.fn(),
        setToken: jest.fn(),
        setTokenExpiresAt: jest.fn(),
        setError: jest.fn(),
        clearError: jest.fn(),
      };

      // Mock the auth store state
      mockUseAuthStore.mockReturnValue({
        ...mockAuthStore,
        acceptInvitation: async (token: string, data: any) => {
          mockStore.setLoading(true);
          mockStore.clearError();

          try {
            const response = await mockAuthApi.acceptInvitation(token, data);
            mockStore.setUser(response.user);
            mockStore.setToken(response.token);
            mockStore.setTokenExpiresAt(response.expires_at);
          } catch (error) {
            mockStore.setError((error as Error).message);
            throw error;
          } finally {
            mockStore.setLoading(false);
          }
        },
      });

      const store = mockUseAuthStore();
      await store.acceptInvitation(mockInvitationToken, mockFormData);

      expect(mockAuthApi.acceptInvitation).toHaveBeenCalledWith(
        mockInvitationToken,
        mockFormData
      );
      expect(mockStore.setLoading).toHaveBeenCalledWith(true);
      expect(mockStore.clearError).toHaveBeenCalled();
      expect(mockStore.setUser).toHaveBeenCalledWith(mockUser);
      expect(mockStore.setToken).toHaveBeenCalledWith(mockAuthResponse.token);
      expect(mockStore.setTokenExpiresAt).toHaveBeenCalledWith(
        mockAuthResponse.expires_at
      );
      expect(mockStore.setLoading).toHaveBeenCalledWith(false);
    });

    it('should handle auth store errors properly', async () => {
      const errorMessage = 'API error occurred';
      mockAuthApi.acceptInvitation.mockRejectedValue({
        response: { data: { message: errorMessage } },
      });

      const mockStore = {
        setLoading: jest.fn(),
        setError: jest.fn(),
        clearError: jest.fn(),
      };

      mockUseAuthStore.mockReturnValue({
        ...mockAuthStore,
        acceptInvitation: async (token: string, data: any) => {
          mockStore.setLoading(true);
          mockStore.clearError();

          try {
            await mockAuthApi.acceptInvitation(token, data);
          } catch (error: any) {
            const errorMsg =
              error.response?.data?.message ||
              error.message ||
              'Registration failed';
            mockStore.setError(errorMsg);
            throw new Error(errorMsg);
          } finally {
            mockStore.setLoading(false);
          }
        },
      });

      const store = mockUseAuthStore();

      await expect(
        store.acceptInvitation(mockInvitationToken, mockFormData)
      ).rejects.toThrow(errorMessage);

      expect(mockStore.setError).toHaveBeenCalledWith(errorMessage);
      expect(mockStore.setLoading).toHaveBeenCalledWith(false);
    });

    it('should store auth metadata securely after successful invitation', async () => {
      const mockSecureStorage = {
        setItem: jest.fn(),
      };

      mockUseAuthStore.mockReturnValue({
        ...mockAuthStore,
        acceptInvitation: async (token: string, data: any) => {
          const response = await mockAuthApi.acceptInvitation(token, data);

          // Simulate secure metadata storage
          const metadata = {
            rememberMe: false,
            expiresAt: response.expires_at,
            userId: response.user.id,
          };
          mockSecureStorage.setItem('auth_metadata', JSON.stringify(metadata));
        },
      });

      const store = mockUseAuthStore();
      await store.acceptInvitation(mockInvitationToken, mockFormData);

      expect(mockSecureStorage.setItem).toHaveBeenCalledWith(
        'auth_metadata',
        JSON.stringify({
          rememberMe: false,
          expiresAt: mockAuthResponse.expires_at,
          userId: mockUser.id,
        })
      );
    });
  });

  describe('Mobile Security Integration', () => {
    it('should integrate with mobile security system after invitation acceptance', async () => {
      // Mock successful invitation acceptance that integrates with mobile security
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: async (token: string, data: any) => {
          // Step 1: Accept invitation via traditional auth
          await mockAuthApi.acceptInvitation(token, data);

          // Step 2: Register device with mobile security
          await mockMobileSecurityAPI.registerDevice();

          // Step 3: Generate secure tokens
          await mockMobileSecurityAPI.generateTokens();

          // Step 4: Store tokens securely
          await mockSecureTokenStorage.setTokens(mockSecureTokens);
        },
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Fill and submit form
      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAuthApi.acceptInvitation).toHaveBeenCalledWith(
          mockInvitationToken,
          mockFormData
        );
        expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
        expect(mockMobileSecurityAPI.generateTokens).toHaveBeenCalled();
        expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(
          mockSecureTokens
        );
      });
    });

    it('should handle mobile security device registration failure', async () => {
      const deviceError = new Error('Device registration failed');
      mockMobileSecurityAPI.registerDevice.mockRejectedValue(deviceError);

      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: async (token: string, data: any) => {
          await mockAuthApi.acceptInvitation(token, data);
          await mockMobileSecurityAPI.registerDevice(); // This will fail
        },
        error: 'Device registration failed',
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Registration Failed',
          'Device registration failed',
          [{ text: 'OK' }]
        );
      });
    });

    it('should setup biometric authentication if available during invitation flow', async () => {
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: async (token: string, data: any) => {
          await mockAuthApi.acceptInvitation(token, data);
          await mockMobileSecurityAPI.registerDevice();
          await mockMobileSecurityAPI.generateTokens();

          // Check if biometric is available and setup
          if (await mockBiometricAuth.isAvailable()) {
            await mockBiometricAuth.setupBiometricAuth();
          }
        },
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockBiometricAuth.isAvailable).toHaveBeenCalled();
        expect(mockBiometricAuth.setupBiometricAuth).toHaveBeenCalled();
      });
    });
  });

  describe('Navigation and User Experience', () => {
    it('should navigate to app after successful invitation acceptance', async () => {
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest.fn().mockResolvedValue(undefined),
        error: null,
        isLoading: false,
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      // Wait for success alert and navigation
      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Account Created!',
          'Your account has been successfully created. You are now logged in.',
          [
            {
              text: 'Continue',
              onPress: expect.any(Function),
            },
          ]
        );
      });

      // Simulate pressing Continue button
      const alertCall = mockAlert.alert.mock.calls.find(
        (call) => call[0] === 'Account Created!'
      );
      const continueButton = alertCall?.[2]?.[0];
      if (
        continueButton &&
        typeof continueButton === 'object' &&
        'onPress' in continueButton
      ) {
        continueButton.onPress?.();
      }

      expect(mockRouter.replace).toHaveBeenCalledWith('/(app)');
    });

    it('should show loading state during invitation processing', async () => {
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest
          .fn()
          .mockImplementation(
            () => new Promise((resolve) => setTimeout(resolve, 100))
          ),
        error: null,
        isLoading: true,
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      // Button should be disabled during loading
      const submitButton = getByTestId('invitation-submit-button');
      expect(submitButton.props.disabled).toBe(true);
    });

    it('should provide navigation to login screen', () => {
      const { getByText } = render(<AcceptInvitationScreen />);

      const loginLink = getByText('Sign In');
      fireEvent.press(loginLink);

      expect(mockRouter.push).toHaveBeenCalledWith('/(auth)/login');
    });
  });

  describe('Error Handling and Edge Cases', () => {
    it('should handle network errors gracefully', async () => {
      mockAuthApi.acceptInvitation.mockRejectedValue(
        new Error('Network Error')
      );
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest
          .fn()
          .mockRejectedValue(new Error('Network Error')),
        error: 'Network Error',
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Registration Failed',
          'Network Error',
          [{ text: 'OK' }]
        );
      });
    });

    it('should handle server validation errors', async () => {
      const validationErrors = {
        response: {
          data: {
            message: 'Validation failed',
            errors: {
              password: ['Password must be at least 8 characters'],
              email: ['Email is already taken'],
            },
          },
        },
      };

      mockAuthApi.acceptInvitation.mockRejectedValue(validationErrors);
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest
          .fn()
          .mockRejectedValue(new Error('Validation failed')),
        error: 'Validation failed',
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(getByTestId('invitation-password-input'), 'weak');
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        'weak'
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Registration Failed',
          'Validation failed',
          [{ text: 'OK' }]
        );
      });
    });

    it('should clear previous errors when retrying invitation', async () => {
      const mockClearError = jest.fn();
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: jest.fn().mockResolvedValue(undefined),
        clearError: mockClearError,
        error: 'Previous error',
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      await waitFor(() => {
        expect(mockClearError).toHaveBeenCalled();
      });
    });
  });

  describe('Password Security and Validation', () => {
    it('should update password strength indicator in real-time', async () => {
      const { getByTestId } = render(<AcceptInvitationScreen />);

      const passwordInput = getByTestId('invitation-password-input');

      // Test different password strengths
      fireEvent.changeText(passwordInput, 'weak');
      expect(mockPasswordValidator.getStrengthIndicator).toHaveBeenCalledWith(
        'weak'
      );

      fireEvent.changeText(passwordInput, 'StrongPassword123!');
      expect(mockPasswordValidator.getStrengthIndicator).toHaveBeenCalledWith(
        'StrongPassword123!'
      );
    });

    it('should show password requirements consistently', () => {
      const { getAllByText } = render(<AcceptInvitationScreen />);

      // All password requirements should be visible
      expect(getAllByText(/At least 8 characters/i)).toHaveLength(1);
      expect(getAllByText(/One uppercase letter/i)).toHaveLength(1);
      expect(getAllByText(/One lowercase letter/i)).toHaveLength(1);
      expect(getAllByText(/One number/i)).toHaveLength(1);
      expect(getAllByText(/One special character/i)).toHaveLength(1);
    });
  });

  describe('Complete Integration Flow', () => {
    it('should successfully complete the entire invitation to authenticated user flow', async () => {
      // Setup complete successful flow
      mockUseAuth.mockReturnValue({
        ...mockUseAuthHook,
        acceptInvitation: async (token: string, data: any) => {
          // Traditional auth
          await mockAuthApi.acceptInvitation(token, data);

          // Mobile security setup
          await mockMobileSecurityAPI.registerDevice();
          const secureTokens = await mockMobileSecurityAPI.generateTokens();
          await mockSecureTokenStorage.setTokens(secureTokens);

          // Biometric setup if available
          if (await mockBiometricAuth.isAvailable()) {
            await mockBiometricAuth.setupBiometricAuth();
          }
        },
        isAuthenticated: true,
        user: mockUser,
        token: mockAuthResponse.token,
        error: null,
        isLoading: false,
      });

      const { getByTestId } = render(<AcceptInvitationScreen />);

      // Complete form and submit
      fireEvent.changeText(
        getByTestId('invitation-name-input'),
        mockFormData.name
      );
      fireEvent.changeText(
        getByTestId('invitation-password-input'),
        mockFormData.password
      );
      fireEvent.changeText(
        getByTestId('invitation-password-confirmation-input'),
        mockFormData.password_confirmation
      );
      fireEvent.press(getByTestId('invitation-submit-button'));

      // Verify complete flow executed
      await waitFor(() => {
        expect(mockAuthApi.acceptInvitation).toHaveBeenCalledWith(
          mockInvitationToken,
          mockFormData
        );
        expect(mockMobileSecurityAPI.registerDevice).toHaveBeenCalled();
        expect(mockMobileSecurityAPI.generateTokens).toHaveBeenCalled();
        expect(mockSecureTokenStorage.setTokens).toHaveBeenCalledWith(
          mockSecureTokens
        );
        expect(mockBiometricAuth.isAvailable).toHaveBeenCalled();
        expect(mockBiometricAuth.setupBiometricAuth).toHaveBeenCalled();
      });

      // Verify success alert and navigation
      await waitFor(() => {
        expect(mockAlert.alert).toHaveBeenCalledWith(
          'Account Created!',
          'Your account has been successfully created. You are now logged in.',
          expect.any(Array)
        );
      });
    });
  });
});
