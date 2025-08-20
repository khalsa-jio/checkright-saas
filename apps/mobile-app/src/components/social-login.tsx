import React from 'react';
import { Alert, Linking } from 'react-native';
import { Button, Text, View } from '@/components/ui';
import type { SocialProvider } from '@/features/auth/types';
import { AuthApi } from '@/features/auth/services/api';

interface SocialLoginProps {
  onSocialLoginSuccess: (authData: any) => void;
  onSocialLoginError: (error: string) => void;
}

const socialProviders: SocialProvider[] = [
  {
    id: 'google',
    name: 'Google',
    color: '#DB4437',
    icon: '🔍', // Using emoji for now, can be replaced with proper icons
  },
  {
    id: 'facebook',
    name: 'Facebook',
    color: '#4267B2',
    icon: '📘',
  },
  {
    id: 'instagram',
    name: 'Instagram',
    color: '#E4405F',
    icon: '📷',
  },
];

export const SocialLogin = ({ onSocialLoginSuccess, onSocialLoginError }: SocialLoginProps) => {
  const handleSocialLogin = async (provider: SocialProvider) => {
    try {
      // Step 1: Initialize OAuth flow
      const initResponse = await AuthApi.initializeOAuth(provider.id, {
        deviceId: 'mobile-device', // Should be actual device ID
        appVersion: '1.0.0', // Should come from app config
      });

      if (!initResponse.success) {
        throw new Error('Failed to initialize OAuth flow');
      }

      const { authorization_url, state } = initResponse.data;

      // Step 2: Open browser for OAuth authorization
      const supported = await Linking.canOpenURL(authorization_url);
      if (!supported) {
        throw new Error('Cannot open OAuth URL');
      }

      await Linking.openURL(authorization_url);

      // Note: In a real implementation, you would need to handle the callback
      // This would typically involve deep linking or a custom URL scheme
      // For now, this is a basic structure showing the OAuth initialization
      
      Alert.alert(
        'OAuth Started',
        `Please complete authentication in your browser. State: ${state}`,
        [
          {
            text: 'I completed authentication',
            onPress: () => {
              // In a real app, this would be handled by deep linking callback
              Alert.prompt(
                'Enter Authorization Code',
                'Please enter the authorization code from the redirect URL:',
                [
                  {
                    text: 'Cancel',
                    style: 'cancel',
                  },
                  {
                    text: 'Submit',
                    onPress: async (code) => {
                      if (code) {
                        try {
                          const authResponse = await AuthApi.completeOAuth(provider.id, {
                            code,
                            state,
                          });
                          onSocialLoginSuccess(authResponse);
                        } catch (error) {
                          onSocialLoginError(error instanceof Error ? error.message : 'OAuth completion failed');
                        }
                      }
                    },
                  },
                ],
                'plain-text'
              );
            },
          },
          {
            text: 'Cancel',
            style: 'cancel',
          },
        ]
      );
    } catch (error) {
      onSocialLoginError(error instanceof Error ? error.message : 'Social login failed');
    }
  };

  return (
    <View className="mt-6">
      <View className="flex-row items-center mb-4">
        <View className="flex-1 h-px bg-gray-300" />
        <Text className="mx-4 text-gray-500 text-sm">Or continue with</Text>
        <View className="flex-1 h-px bg-gray-300" />
      </View>

      <View className="space-y-3">
        {socialProviders.map((provider) => (
          <Button
            key={provider.id}
            label={`${provider.icon} Continue with ${provider.name}`}
            variant="outline"
            onPress={() => handleSocialLogin(provider)}
            className="w-full p-4 border border-gray-300 rounded-lg"
            style={{ borderColor: provider.color }}
          />
        ))}
      </View>
    </View>
  );
};
