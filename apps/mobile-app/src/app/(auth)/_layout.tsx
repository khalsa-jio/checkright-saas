import { Redirect, Stack } from 'expo-router';

import { useAuth } from '@/features/auth/hooks/useAuth';

export default function AuthLayout() {
  const { isAuthenticated } = useAuth();

  // Redirect to main app if already authenticated
  if (isAuthenticated) {
    return <Redirect href="/(app)" />;
  }

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: '#ffffff' },
      }}
    >
      <Stack.Screen
        name="login"
        options={{
          title: 'Login',
          presentation: 'card',
        }}
      />
      <Stack.Screen
        name="accept-invitation"
        options={{
          title: 'Accept Invitation',
          presentation: 'card',
        }}
      />
    </Stack>
  );
}
