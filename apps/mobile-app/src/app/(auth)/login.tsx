import { zodResolver } from '@hookform/resolvers/zod';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FocusAwareStatusBar } from '@/components/ui/focus-aware-status-bar';
import { ControlledInput } from '@/components/ui/input';
import { Text } from '@/components/ui/text';
import { SocialLogin } from '@/components/social-login';
import { useAuth } from '@/features/auth/hooks/useAuth';

const loginSchema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginFormData = z.infer<typeof loginSchema>;

export default function LoginScreen() {
  const [rememberMe, setRememberMe] = useState(false);
  const { login, isLoading, error, clearError } = useAuth();

  const form = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const onSubmit = async (data: LoginFormData) => {
    try {
      clearError();
      await login(data.email, data.password, rememberMe);

      // Navigation will be handled by auth state change
      router.replace('/(app)');
    } catch (error: any) {
      Alert.alert(
        'Login Failed',
        error.message || 'Please check your credentials and try again.',
        [{ text: 'OK' }]
      );
    }
  };

  const handleSocialLoginSuccess = (authData: any) => {
    try {
      // Handle successful social login
      console.log('Social login successful:', authData);
      router.replace('/(app)');
    } catch (error) {
      console.error('Social login success handler error:', error);
    }
  };

  const handleSocialLoginError = (error: string) => {
    Alert.alert('Social Login Failed', error, [{ text: 'OK' }]);
  };

  return (
    <SafeAreaView className="flex-1 bg-white dark:bg-black">
      <FocusAwareStatusBar />
      <ScrollView
        className="flex-1"
        contentContainerStyle={{ flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
      >
        <View className="flex-1 justify-center px-6">
          {/* Header */}
          <View className="mb-8">
            <Text className="mb-2 text-center text-3xl font-bold text-black dark:text-white">
              Welcome Back
            </Text>
            <Text className="text-center text-base text-neutral-600 dark:text-neutral-400">
              Sign in to your CheckRight account
            </Text>
          </View>

          {/* Error Message */}
          {error && (
            <View className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
              <Text className="text-center text-sm text-red-600 dark:text-red-400">
                {error}
              </Text>
            </View>
          )}

          {/* Login Form */}
          <View className="space-y-4">
            <ControlledInput
              control={form.control}
              name="email"
              label="Email"
              placeholder="Enter your email"
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              textContentType="emailAddress"
              testID="login-email-input"
            />

            <ControlledInput
              control={form.control}
              name="password"
              label="Password"
              placeholder="Enter your password"
              secureTextEntry
              autoComplete="password"
              textContentType="password"
              testID="login-password-input"
            />

            {/* Remember Me Checkbox */}
            <View className="mt-4 flex-row items-center justify-between">
              <Checkbox
                checked={rememberMe}
                onChange={setRememberMe}
                label="Remember me"
                accessibilityLabel="Remember me for 30 days"
                testID="login-remember-me-checkbox"
              />

              <Button
                variant="ghost"
                size="sm"
                label="Forgot Password?"
                onPress={() => {
                  // TODO: Implement forgot password flow
                  Alert.alert(
                    'Coming Soon',
                    'Forgot password feature will be available soon.'
                  );
                }}
                testID="login-forgot-password-button"
              />
            </View>

            {/* Login Button */}
            <Button
              label="Sign In"
              onPress={form.handleSubmit(onSubmit)}
              loading={isLoading}
              disabled={isLoading}
              className="mt-6"
              testID="login-submit-button"
            />
          </View>

          {/* Social Login */}
          <SocialLogin
            onSocialLoginSuccess={handleSocialLoginSuccess}
            onSocialLoginError={handleSocialLoginError}
          />

          {/* Footer */}
          <View className="mt-8">
            <Text className="text-center text-sm text-neutral-600 dark:text-neutral-400">
              Don't have an account? Check your email for an invitation link.
            </Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}
