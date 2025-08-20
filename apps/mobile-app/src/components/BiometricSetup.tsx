import React, { useEffect, useState } from 'react';
import { Alert, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { useSecureAuth } from '@/lib/auth/secure-auth';
import { biometricAuth } from '@/lib/biometric-auth';

interface BiometricSetupProps {
  onComplete?: (enabled: boolean) => void;
  showSkip?: boolean;
}

export const BiometricSetup: React.FC<BiometricSetupProps> = ({
  onComplete,
  showSkip = true,
}) => {
  const [isAvailable, setIsAvailable] = useState(false);
  const [biometricType, setBiometricType] = useState<string>('');
  const [isLoading, setIsLoading] = useState(false);
  const [isChecking, setIsChecking] = useState(true);

  const enableBiometric = useSecureAuth.use.enableBiometric();
  const disableBiometric = useSecureAuth.use.disableBiometric();

  useEffect(() => {
    checkBiometricAvailability();
  }, []);

  const checkBiometricAvailability = async () => {
    try {
      setIsChecking(true);
      const available = await biometricAuth.isAvailable();
      const description = await biometricAuth.getBiometricDescription();

      setIsAvailable(available);
      setBiometricType(description);
    } catch (error) {
      console.error('Failed to check biometric availability:', error);
      setIsAvailable(false);
    } finally {
      setIsChecking(false);
    }
  };

  const handleEnableBiometric = async () => {
    try {
      setIsLoading(true);

      await enableBiometric();

      Alert.alert(
        'Success',
        'Biometric authentication has been enabled for your account.',
        [
          {
            text: 'OK',
            onPress: () => onComplete?.(true),
          },
        ]
      );
    } catch (error: any) {
      Alert.alert(
        'Setup Failed',
        error.message ||
          'Failed to enable biometric authentication. Please try again.',
        [{ text: 'OK' }]
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleSkip = () => {
    onComplete?.(false);
  };

  if (isChecking) {
    return (
      <View className="flex-1 items-center justify-center p-6">
        <Text className="text-center text-gray-600">
          Checking biometric capabilities...
        </Text>
      </View>
    );
  }

  if (!isAvailable) {
    return (
      <View className="flex-1 items-center justify-center p-6">
        <Text className="mb-4 text-center text-xl font-bold">
          Biometric Authentication Unavailable
        </Text>

        <Text className="mb-8 text-center text-gray-600">
          Biometric authentication is not available on this device or hasn't
          been set up in your device settings.
        </Text>

        {showSkip && (
          <Button onPress={handleSkip} className="w-full">
            Continue without Biometric
          </Button>
        )}
      </View>
    );
  }

  return (
    <View className="flex-1 items-center justify-center p-6">
      <Text className="mb-4 text-center text-2xl font-bold">
        Enable {biometricType}
      </Text>

      <Text className="mb-8 text-center text-gray-600">
        Secure your account with {biometricType.toLowerCase()} for quick and
        secure access to your data.
      </Text>

      <View className="w-full space-y-4">
        <Button
          onPress={handleEnableBiometric}
          disabled={isLoading}
          className="w-full"
        >
          {isLoading ? 'Setting up...' : `Enable ${biometricType}`}
        </Button>

        {showSkip && (
          <Button
            variant="outline"
            onPress={handleSkip}
            disabled={isLoading}
            className="w-full"
          >
            Skip for Now
          </Button>
        )}
      </View>

      <Text className="mt-6 text-center text-xs text-gray-500">
        You can change this setting later in your account preferences.
      </Text>
    </View>
  );
};

// Biometric authentication prompt component
interface BiometricPromptProps {
  isVisible: boolean;
  title?: string;
  subtitle?: string;
  onSuccess: () => void;
  onError: (error: string) => void;
  onCancel: () => void;
}

export const BiometricPrompt: React.FC<BiometricPromptProps> = ({
  isVisible,
  title = 'Authenticate',
  subtitle = 'Use your biometric to continue',
  onSuccess,
  onError,
  onCancel,
}) => {
  const [isAuthenticating, setIsAuthenticating] = useState(false);

  useEffect(() => {
    if (isVisible && !isAuthenticating) {
      performAuthentication();
    }
  }, [isVisible]);

  const performAuthentication = async () => {
    try {
      setIsAuthenticating(true);

      const result = await biometricAuth.authenticate(subtitle);

      if (result.success) {
        onSuccess();
      } else {
        onError(result.error || 'Authentication failed');
      }
    } catch (error: any) {
      onError(error.message || 'Authentication failed');
    } finally {
      setIsAuthenticating(false);
    }
  };

  if (!isVisible) {
    return null;
  }

  return (
    <View className="flex-1 items-center justify-center bg-black/50 p-6">
      <View className="w-full max-w-sm rounded-lg bg-white p-6">
        <Text className="mb-2 text-center text-xl font-bold">{title}</Text>

        <Text className="mb-6 text-center text-gray-600">{subtitle}</Text>

        {isAuthenticating ? (
          <Text className="text-center text-blue-600">
            Waiting for authentication...
          </Text>
        ) : (
          <View className="space-y-3">
            <Button onPress={performAuthentication} className="w-full">
              Try Again
            </Button>

            <Button variant="outline" onPress={onCancel} className="w-full">
              Cancel
            </Button>
          </View>
        )}
      </View>
    </View>
  );
};
