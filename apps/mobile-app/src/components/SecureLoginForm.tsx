import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, View } from 'react-native';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Text } from '@/components/ui/text';
import { useSecureAuth } from '@/lib/auth/secure-auth';
import { biometricAuth } from '@/lib/biometric-auth';

import { BiometricPrompt } from './BiometricSetup';

interface SecureLoginFormProps {
  onLoginSuccess?: () => void;
  onShowBiometricSetup?: () => void;
}

export const SecureLoginForm: React.FC<SecureLoginFormProps> = ({
  onLoginSuccess,
  onShowBiometricSetup,
}) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showBiometricPrompt, setShowBiometricPrompt] = useState(false);
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [biometricType, setBiometricType] = useState('');

  const signIn = useSecureAuth.use.signIn();
  const authenticateWithBiometric =
    useSecureAuth.use.authenticateWithBiometric();
  const status = useSecureAuth.use.status();
  const error = useSecureAuth.use.error();
  const clearError = useSecureAuth.use.clearError();
  const biometricEnabled = useSecureAuth.use.biometricEnabled();

  useEffect(() => {
    checkBiometricAvailability();
  }, []);

  useEffect(() => {
    if (status === 'authenticated') {
      onLoginSuccess?.();
    }
  }, [status]);

  const checkBiometricAvailability = async () => {
    try {
      const available = await biometricAuth.isAvailable();
      const description = await biometricAuth.getBiometricDescription();

      setBiometricAvailable(available);
      setBiometricType(description);
    } catch (error) {
      console.error('Failed to check biometric availability:', error);
    }
  };

  const handleLogin = async () => {
    if (!email.trim() || !password.trim()) {
      Alert.alert('Error', 'Please enter both email and password.');
      return;
    }

    try {
      setIsLoading(true);
      clearError();

      // Perform secure sign in
      await signIn(email.trim().toLowerCase(), password, false);

      // If biometric is available and user hasn't set it up, offer setup
      if (biometricAvailable && !biometricEnabled && onShowBiometricSetup) {
        setTimeout(() => {
          Alert.alert(
            `Enable ${biometricType}?`,
            `Would you like to enable ${biometricType.toLowerCase()} for faster, secure access to your account?`,
            [
              { text: 'Not Now', style: 'cancel' },
              { text: 'Enable', onPress: () => onShowBiometricSetup() },
            ]
          );
        }, 500);
      }
    } catch (err: any) {
      Alert.alert(
        'Login Failed',
        err.message || 'Please check your credentials and try again.',
        [{ text: 'OK' }]
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleBiometricLogin = async () => {
    try {
      setShowBiometricPrompt(true);
    } catch (error: any) {
      Alert.alert('Error', error.message || 'Biometric authentication failed');
    }
  };

  const onBiometricSuccess = () => {
    setShowBiometricPrompt(false);
    // In a real implementation, you'd have stored credentials that you can use
    // For this demo, we'll just show a message
    Alert.alert(
      'Feature Coming Soon',
      'Full biometric login will be available in the next update. For now, please use email and password.',
      [{ text: 'OK' }]
    );
  };

  const onBiometricError = (errorMessage: string) => {
    setShowBiometricPrompt(false);
    Alert.alert('Authentication Failed', errorMessage);
  };

  const onBiometricCancel = () => {
    setShowBiometricPrompt(false);
  };

  return (
    <>
      <View className="w-full space-y-4">
        <Text className="mb-6 text-center text-2xl font-bold">
          Secure Login
        </Text>

        {error && (
          <View className="rounded-lg border border-red-200 bg-red-50 p-3">
            <Text className="text-sm text-red-700">{error}</Text>
          </View>
        )}

        <Input
          placeholder="Email"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          editable={!isLoading}
        />

        <Input
          placeholder="Password"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          autoCapitalize="none"
          autoCorrect={false}
          editable={!isLoading}
        />

        <Button
          onPress={handleLogin}
          disabled={isLoading || !email.trim() || !password.trim()}
          className="w-full"
        >
          {isLoading ? (
            <View className="flex-row items-center">
              <ActivityIndicator size="small" color="white" className="mr-2" />
              <Text className="text-white">Signing In...</Text>
            </View>
          ) : (
            'Sign In'
          )}
        </Button>

        {biometricAvailable && biometricEnabled && (
          <>
            <View className="my-4 flex-row items-center">
              <View className="h-px flex-1 bg-gray-300" />
              <Text className="mx-4 text-gray-500">or</Text>
              <View className="h-px flex-1 bg-gray-300" />
            </View>

            <Button
              variant="outline"
              onPress={handleBiometricLogin}
              disabled={isLoading}
              className="w-full"
            >
              <Text>Use {biometricType}</Text>
            </Button>
          </>
        )}

        <View className="mt-6 rounded-lg bg-blue-50 p-4">
          <Text className="mb-2 text-sm font-medium text-blue-800">
            🔒 Enhanced Security Features:
          </Text>
          <Text className="text-xs text-blue-700">
            • Device fingerprinting and binding{'\n'}• Encrypted token storage
            {'\n'}• Automatic token rotation{'\n'}• Biometric authentication
            support
          </Text>
        </View>
      </View>

      <BiometricPrompt
        isVisible={showBiometricPrompt}
        title={`Sign in with ${biometricType}`}
        subtitle="Authenticate to access your account"
        onSuccess={onBiometricSuccess}
        onError={onBiometricError}
        onCancel={onBiometricCancel}
      />
    </>
  );
};
