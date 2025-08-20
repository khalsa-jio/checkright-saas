import { zodResolver } from '@hookform/resolvers/zod';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { FocusAwareStatusBar } from '@/components/ui/focus-aware-status-bar';
import { ControlledInput } from '@/components/ui/input';
import { ProgressBar, type ProgressBarRef } from '@/components/ui/progress-bar';
import { Text } from '@/components/ui/text';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { PasswordValidator } from '@/features/auth/utils/passwordValidation';

const invitationSchema = z
  .object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ['password_confirmation'],
  });

type InvitationFormData = z.infer<typeof invitationSchema>;

export default function AcceptInvitationScreen() {
  const { token } = useLocalSearchParams<{ token: string }>();
  const [passwordStrength, setPasswordStrength] = useState({
    progress: 0,
    label: 'Very Weak',
    color: 'rgb(239, 68, 68)',
  });
  const progressBarRef = useRef<ProgressBarRef>(null);
  const { acceptInvitation, isLoading, error, clearError } = useAuth();

  const form = useForm<InvitationFormData>({
    resolver: zodResolver(invitationSchema),
    defaultValues: {
      name: '',
      password: '',
      password_confirmation: '',
    },
  });

  const watchPassword = form.watch('password');

  // Update password strength indicator
  useEffect(() => {
    if (watchPassword) {
      const strength = PasswordValidator.getStrengthIndicator(watchPassword);
      setPasswordStrength(strength);
      progressBarRef.current?.setProgress(strength.progress);
    } else {
      setPasswordStrength({
        progress: 0,
        label: 'Very Weak',
        color: 'rgb(239, 68, 68)',
      });
      progressBarRef.current?.setProgress(0);
    }
  }, [watchPassword]);

  const onSubmit = async (data: InvitationFormData) => {
    if (!token) {
      Alert.alert(
        'Invalid Link',
        'This invitation link is invalid or has expired.'
      );
      return;
    }

    try {
      clearError();
      await acceptInvitation(token, data);

      Alert.alert(
        'Account Created!',
        'Your account has been successfully created. You are now logged in.',
        [
          {
            text: 'Continue',
            onPress: () => router.replace('/(app)'),
          },
        ]
      );
    } catch (error: any) {
      Alert.alert(
        'Registration Failed',
        error.message || 'Failed to create your account. Please try again.',
        [{ text: 'OK' }]
      );
    }
  };

  // Show error if no token provided
  if (!token) {
    return (
      <SafeAreaView className="flex-1 bg-white dark:bg-black">
        <FocusAwareStatusBar />
        <View className="flex-1 items-center justify-center px-6">
          <Text className="mb-4 text-center text-xl font-bold text-red-600">
            Invalid Invitation Link
          </Text>
          <Text className="mb-6 text-center text-base text-neutral-600 dark:text-neutral-400">
            This invitation link is invalid or has expired. Please request a new
            invitation.
          </Text>
          <Button
            label="Go to Login"
            onPress={() => router.replace('/(auth)/login')}
            variant="outline"
          />
        </View>
      </SafeAreaView>
    );
  }

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
              Create Your Account
            </Text>
            <Text className="text-center text-base text-neutral-600 dark:text-neutral-400">
              Complete your registration to access CheckRight
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

          {/* Registration Form */}
          <View className="space-y-4">
            <ControlledInput
              control={form.control}
              name="name"
              label="Full Name"
              placeholder="Enter your full name"
              autoCapitalize="words"
              autoComplete="name"
              textContentType="name"
              testID="invitation-name-input"
            />

            <View>
              <ControlledInput
                control={form.control}
                name="password"
                label="Password"
                placeholder="Create a strong password"
                secureTextEntry
                autoComplete="new-password"
                textContentType="newPassword"
                testID="invitation-password-input"
              />

              {/* Password Strength Indicator */}
              {watchPassword && (
                <View className="mt-2">
                  <View className="mb-1 flex-row items-center justify-between">
                    <Text className="text-sm text-neutral-600 dark:text-neutral-400">
                      Password Strength
                    </Text>
                    <Text
                      className="text-sm font-medium"
                      style={{ color: passwordStrength.color }}
                    >
                      {passwordStrength.label}
                    </Text>
                  </View>
                  <ProgressBar ref={progressBarRef} className="h-2" />
                </View>
              )}

              {/* Password Requirements */}
              <View className="mt-3">
                <Text className="mb-2 text-sm text-neutral-600 dark:text-neutral-400">
                  Password must include:
                </Text>
                {PasswordValidator.getRequirements().map(
                  (requirement, index) => (
                    <Text
                      key={index}
                      className="ml-2 text-xs text-neutral-500 dark:text-neutral-500"
                    >
                      • {requirement}
                    </Text>
                  )
                )}
              </View>
            </View>

            <ControlledInput
              control={form.control}
              name="password_confirmation"
              label="Confirm Password"
              placeholder="Confirm your password"
              secureTextEntry
              autoComplete="new-password"
              textContentType="newPassword"
              testID="invitation-password-confirmation-input"
            />

            {/* Create Account Button */}
            <Button
              label="Create Account"
              onPress={form.handleSubmit(onSubmit)}
              loading={isLoading}
              disabled={isLoading}
              className="mt-6"
              testID="invitation-submit-button"
            />
          </View>

          {/* Footer */}
          <View className="mt-8">
            <Text className="text-center text-sm text-neutral-600 dark:text-neutral-400">
              Already have an account?{' '}
              <Text
                className="text-blue-600 underline dark:text-blue-400"
                onPress={() => router.push('/(auth)/login')}
              >
                Sign In
              </Text>
            </Text>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}
