import '@testing-library/react-native/extend-expect';


// react-hook form setup for testing
// @ts-ignore
global.window = {};
// @ts-ignore
global.window = global;

// Mock the entire React Native CSS Interop ecosystem
jest.mock('react-native-css-interop/src/runtime/native/appearance-observables', () => ({
  resetAppearanceListeners: jest.fn(),
  addAppearanceListener: jest.fn(),
  removeAppearanceListener: jest.fn(),
  get globalAppearanceListeners() {
    return [];
  },
  appearanceObserver: {
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  },
}));

jest.mock('react-native-css-interop/src/runtime/native/api', () => ({
  reset: jest.fn(),
  initialize: jest.fn(),
}));

jest.mock('react-native-css-interop/src/runtime/api.native', () => ({}));

jest.mock('react-native-css-interop/src/runtime/wrap-jsx', () => ({
  wrapJSX: jest.fn((Component) => Component),
}));

// Mock expo-secure-store
jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  getItemAsync: jest.fn().mockResolvedValue(null),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
  SecureStoreAccessibility: {
    WHEN_UNLOCKED: 'WHEN_UNLOCKED',
    WHEN_UNLOCKED_THIS_DEVICE_ONLY: 'WHEN_UNLOCKED_THIS_DEVICE_ONLY',
  },
}));

// Mock expo-local-authentication
jest.mock('expo-local-authentication', () => ({
  hasHardwareAsync: jest.fn().mockResolvedValue(true),
  isEnrolledAsync: jest.fn().mockResolvedValue(true),
  supportedAuthenticationTypesAsync: jest.fn().mockResolvedValue([1, 2]),
  authenticateAsync: jest.fn().mockResolvedValue({ success: true }),
  AuthenticationType: {
    FINGERPRINT: 1,
    FACIAL_RECOGNITION: 2,
    IRIS: 3,
  },
}));

// Mock expo-crypto
jest.mock('expo-crypto', () => ({
  digestStringAsync: jest.fn().mockResolvedValue('mocked-hash-string'),
  getRandomBytesAsync: jest.fn().mockResolvedValue(new Uint8Array(32)),
  CryptoDigestAlgorithm: {
    SHA256: 'SHA256',
  },
  CryptoEncoding: {
    HEX: 'hex',
  },
}));

// Mock expo-constants
jest.mock('expo-constants', () => ({
  default: {
    installationId: 'mock-installation-id',
    expoConfig: {
      version: '1.0.0',
      name: 'test-app',
      extra: {
        buildNumber: '1',
      },
    },
    platform: {
      ios: {
        model: 'iPhone',
      },
      android: {
        manufacturer: 'Google',
      },
    },
  },
}));

// Mock expo-localization
jest.mock('expo-localization', () => ({
  locale: 'en-US',
  timezone: 'America/New_York',
}));

// Mock react-native platform and dimensions
jest.mock('react-native', () => {
  // Create a complete appearance mock
  const AppearanceMock = {
    getColorScheme: jest.fn().mockReturnValue('light'),
    addChangeListener: jest.fn(),
    removeChangeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    setColorScheme: jest.fn(),
  };

  // Set up on global object as well
  if (typeof global !== 'undefined') {
    (global as any).Appearance = AppearanceMock;
  }

  return {
    Platform: {
      OS: 'ios',
      Version: '17.0',
    },
    Dimensions: {
      get: jest.fn().mockReturnValue({ width: 375, height: 812, scale: 3, fontScale: 1 }),
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
    },
    Alert: {
      alert: jest.fn(),
    },
    StyleSheet: {
      create: jest.fn((styles) => styles),
    },
    Appearance: AppearanceMock,
    View: 'View',
    Text: 'Text',
    TouchableOpacity: 'TouchableOpacity',
    ScrollView: 'ScrollView',
    SafeAreaView: 'SafeAreaView',
    Pressable: 'Pressable',
    ActivityIndicator: 'ActivityIndicator',
  };
});

// Mock API client
jest.mock('@/api/common/client', () => ({
  client: {
    post: jest.fn().mockResolvedValue({ data: {} }),
    get: jest.fn().mockResolvedValue({ data: {} }),
    delete: jest.fn().mockResolvedValue({ data: {} }),
  },
}));

// Mock navigation
jest.mock('expo-router', () => ({
  router: {
    replace: jest.fn(),
    push: jest.fn(),
    back: jest.fn(),
  },
  useLocalSearchParams: jest.fn().mockReturnValue({ token: 'mock-token' }),
}));

// Mock MMKV for storage
jest.mock('react-native-mmkv', () => ({
  MMKV: jest.fn().mockImplementation(() => ({
    getString: jest.fn(),
    set: jest.fn(),
    delete: jest.fn(),
  })),
}));

// Mock zustand persist
jest.mock('zustand/middleware', () => ({
  persist: (fn: any) => fn,
  createJSONStorage: () => ({
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
  }),
}));

// Mock react-native-safe-area-context
jest.mock('react-native-safe-area-context', () => ({
  SafeAreaView: 'SafeAreaView',
  SafeAreaProvider: ({ children }: { children: React.ReactNode }) => children,
  useSafeAreaInsets: () => ({ top: 44, bottom: 34, left: 0, right: 0 }),
  initialWindowMetrics: {
    insets: { top: 44, bottom: 34, left: 0, right: 0 },
    frame: { x: 0, y: 0, width: 375, height: 812 },
  },
}));

// Mock expo-linking
jest.mock('expo-linking', () => ({
  parse: jest.fn(),
  createURL: jest.fn(),
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  getInitialURL: jest.fn().mockResolvedValue(null),
}));

// Mock react-hook-form
jest.mock('react-hook-form', () => ({
  useForm: () => ({
    control: {},
    handleSubmit: (onSubmit: Function) => (e: any) => onSubmit({}),
    formState: { errors: {}, isValid: true },
    watch: jest.fn(),
    setValue: jest.fn(),
    getValues: jest.fn(),
    reset: jest.fn(),
  }),
  Controller: ({ render }: { render: Function }) => render({
    field: {
      onChange: jest.fn(),
      onBlur: jest.fn(),
      value: '',
      name: 'test',
    },
    fieldState: { error: null },
  }),
}));

// Mock tailwind-variants
jest.mock('tailwind-variants', () => ({
  tv: (config: any) => {
    return {
      slots: config.slots || {},
      base: () => '',
      variants: () => '',
      defaultVariants: config.defaultVariants || {},
    };
  },
}));

// Mock @gorhom/bottom-sheet
jest.mock('@gorhom/bottom-sheet', () => ({
  BottomSheetModalProvider: ({ children }: { children: React.ReactNode }) => children,
  BottomSheetModal: 'BottomSheetModal',
  BottomSheet: 'BottomSheet',
}));

// Mock react-native-css-interop
jest.mock('react-native-css-interop/src/runtime/jsx-runtime', () => ({
  jsx: jest.fn(),
  jsxs: jest.fn(),
  Fragment: 'Fragment',
}));

jest.mock('react-native-css-interop', () => ({
  useTailwindCSSInterop: jest.fn(),
}));
