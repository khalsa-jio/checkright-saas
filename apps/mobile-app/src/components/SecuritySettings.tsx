import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, Switch, View } from 'react-native';

import { mobileSecurityAPI } from '@/api/mobile-security';
import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { useSecureAuth } from '@/lib/auth/secure-auth';
import { biometricAuth } from '@/lib/biometric-auth';
import { deviceFingerprintService } from '@/lib/device-fingerprint';
import { secureStorage } from '@/lib/secure-storage';

interface DeviceInfo {
  device_id: string;
  device_info: any;
  is_trusted: boolean;
  trust_expires_at: string | null;
  last_used_at: string;
  created_at: string;
}

export const SecuritySettings: React.FC = () => {
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [biometricType, setBiometricType] = useState('');
  const [devices, setDevices] = useState<DeviceInfo[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [deviceFingerprint, setDeviceFingerprint] = useState<string>('');

  const biometricEnabled = useSecureAuth.use.biometricEnabled();
  const enableBiometric = useSecureAuth.use.enableBiometric();
  const disableBiometric = useSecureAuth.use.disableBiometric();
  const tokens = useSecureAuth.use.tokens();
  const signOut = useSecureAuth.use.signOut();

  useEffect(() => {
    initializeSecuritySettings();
  }, []);

  const initializeSecuritySettings = async () => {
    try {
      setIsLoading(true);

      // Check biometric capabilities
      const available = await biometricAuth.isAvailable();
      const description = await biometricAuth.getBiometricDescription();
      setBiometricAvailable(available);
      setBiometricType(description);

      // Load device information
      await loadDevices();

      // Get device fingerprint
      const fingerprint = await deviceFingerprintService.getDeviceHash();
      setDeviceFingerprint(fingerprint);
    } catch (error) {
      console.error('Failed to initialize security settings:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const loadDevices = async () => {
    try {
      const deviceList = await mobileSecurityAPI.getDevices();
      setDevices(deviceList);
    } catch (error) {
      console.error('Failed to load devices:', error);
    }
  };

  const handleToggleBiometric = async (enabled: boolean) => {
    try {
      setIsLoading(true);

      if (enabled) {
        await enableBiometric();
        Alert.alert('Success', 'Biometric authentication has been enabled.');
      } else {
        await disableBiometric();
        Alert.alert('Success', 'Biometric authentication has been disabled.');
      }
    } catch (error: any) {
      Alert.alert(
        'Error',
        error.message || 'Failed to update biometric settings.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleRevokeAllTokens = async () => {
    Alert.alert(
      'Revoke All Tokens',
      'This will sign you out from all devices. You will need to sign in again.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Revoke',
          style: 'destructive',
          onPress: async () => {
            try {
              setIsLoading(true);
              await mobileSecurityAPI.revokeDeviceTokens();
              await signOut();
              Alert.alert('Success', 'All tokens have been revoked.');
            } catch (error: any) {
              Alert.alert('Error', error.message || 'Failed to revoke tokens.');
            } finally {
              setIsLoading(false);
            }
          },
        },
      ]
    );
  };

  const handleClearSecureStorage = async () => {
    Alert.alert(
      'Clear Secure Storage',
      'This will remove all securely stored data including tokens and device information. You will be signed out.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear',
          style: 'destructive',
          onPress: async () => {
            try {
              setIsLoading(true);
              await secureStorage.clearAll();
              await signOut();
              Alert.alert('Success', 'Secure storage has been cleared.');
            } catch (error: any) {
              Alert.alert('Error', error.message || 'Failed to clear storage.');
            } finally {
              setIsLoading(false);
            }
          },
        },
      ]
    );
  };

  const handleViewTokenInfo = async () => {
    try {
      setIsLoading(true);
      const tokenInfo = await mobileSecurityAPI.getTokenInfo();

      Alert.alert(
        'Token Information',
        `Token Status: ${tokenInfo.token_info.should_rotate ? 'Needs Rotation' : 'Valid'}\n` +
          `Access Token Expires: ${new Date(tokenInfo.token_info.access_expires_at).toLocaleString()}\n` +
          `Refresh Token Expires: ${new Date(tokenInfo.token_info.refresh_expires_at).toLocaleString()}`,
        [{ text: 'OK' }]
      );
    } catch (error: any) {
      Alert.alert('Error', error.message || 'Failed to get token information.');
    } finally {
      setIsLoading(false);
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
  };

  return (
    <ScrollView className="flex-1 bg-gray-50">
      <View className="space-y-6 p-4">
        {/* Header */}
        <View>
          <Text className="mb-2 text-2xl font-bold">Security Settings</Text>
          <Text className="text-gray-600">
            Manage your account security preferences
          </Text>
        </View>

        {/* Biometric Authentication */}
        <View className="rounded-lg bg-white p-4">
          <View className="mb-3 flex-row items-center justify-between">
            <Text className="text-lg font-semibold">
              Biometric Authentication
            </Text>
            {biometricAvailable && (
              <Switch
                value={biometricEnabled}
                onValueChange={handleToggleBiometric}
                disabled={isLoading}
              />
            )}
          </View>

          {biometricAvailable ? (
            <Text className="text-sm text-gray-600">
              Use {biometricType.toLowerCase()} for secure and convenient access
            </Text>
          ) : (
            <Text className="text-sm text-gray-500">
              Biometric authentication is not available on this device
            </Text>
          )}
        </View>

        {/* Device Information */}
        <View className="rounded-lg bg-white p-4">
          <Text className="mb-3 text-lg font-semibold">Device Information</Text>

          <View className="space-y-2">
            <View className="flex-row justify-between">
              <Text className="text-gray-600">Device ID:</Text>
              <Text className="font-mono text-xs text-gray-900">
                {deviceFingerprint}...
              </Text>
            </View>

            {tokens && (
              <View className="flex-row justify-between">
                <Text className="text-gray-600">Status:</Text>
                <Text className="font-semibold text-green-600">
                  Authenticated
                </Text>
              </View>
            )}
          </View>
        </View>

        {/* Token Management */}
        <View className="rounded-lg bg-white p-4">
          <Text className="mb-3 text-lg font-semibold">Token Management</Text>

          <View className="space-y-3">
            <Button
              variant="outline"
              onPress={handleViewTokenInfo}
              disabled={isLoading || !tokens}
              className="w-full"
            >
              View Token Information
            </Button>

            <Button
              variant="outline"
              onPress={handleRevokeAllTokens}
              disabled={isLoading || !tokens}
              className="w-full"
            >
              Revoke All Tokens
            </Button>
          </View>
        </View>

        {/* Registered Devices */}
        {devices.length > 0 && (
          <View className="rounded-lg bg-white p-4">
            <Text className="mb-3 text-lg font-semibold">
              Registered Devices
            </Text>

            {devices.map((device, index) => (
              <View
                key={device.device_id}
                className="border-b border-gray-200 py-3 last:border-b-0"
              >
                <View className="flex-row items-start justify-between">
                  <View className="flex-1">
                    <Text className="font-medium">
                      {device.device_info.device_name || 'Unknown Device'}
                    </Text>
                    <Text className="text-sm text-gray-600">
                      {device.device_info.platform} •{' '}
                      {device.device_info.device_model}
                    </Text>
                    <Text className="text-xs text-gray-500">
                      Last used: {formatDate(device.last_used_at)}
                    </Text>
                  </View>

                  <View className="items-end">
                    <View
                      className={`rounded px-2 py-1 ${
                        device.is_trusted ? 'bg-green-100' : 'bg-orange-100'
                      }`}
                    >
                      <Text
                        className={`text-xs font-medium ${
                          device.is_trusted
                            ? 'text-green-800'
                            : 'text-orange-800'
                        }`}
                      >
                        {device.is_trusted ? 'Trusted' : 'Untrusted'}
                      </Text>
                    </View>
                  </View>
                </View>
              </View>
            ))}
          </View>
        )}

        {/* Advanced Settings */}
        <View className="rounded-lg bg-white p-4">
          <Text className="mb-3 text-lg font-semibold text-red-600">
            Advanced Settings
          </Text>

          <Text className="mb-4 text-sm text-gray-600">
            These actions will sign you out and require re-authentication.
          </Text>

          <Button
            variant="outline"
            onPress={handleClearSecureStorage}
            disabled={isLoading}
            className="w-full border-red-300"
          >
            <Text className="text-red-600">Clear Secure Storage</Text>
          </Button>
        </View>

        {/* Security Information */}
        <View className="rounded-lg bg-blue-50 p-4">
          <Text className="mb-2 font-semibold text-blue-800">
            🔒 Security Features Active
          </Text>
          <View className="space-y-1">
            <Text className="text-sm text-blue-700">
              ✓ Device fingerprinting and binding
            </Text>
            <Text className="text-sm text-blue-700">
              ✓ Encrypted secure storage (Keychain/KeyStore)
            </Text>
            <Text className="text-sm text-blue-700">
              ✓ Automatic token rotation
            </Text>
            <Text className="text-sm text-blue-700">
              ✓ Request signing and validation
            </Text>
            {biometricEnabled && (
              <Text className="text-sm text-blue-700">
                ✓ Biometric authentication enabled
              </Text>
            )}
          </View>
        </View>
      </View>
    </ScrollView>
  );
};
