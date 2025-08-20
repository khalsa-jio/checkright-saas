
# CheckRight Mobile App

React Native mobile application built with Expo for the CheckRight SaaS platform.

## 🚀 Quick Start

### Prerequisites

- Node.js 20+
- pnpm 8+
- Expo CLI
- iOS Simulator (macOS) or Android Studio
- CheckRight API running locally or staging URL configured

### Installation

1. **Install dependencies**
   ```bash
   pnpm install
   ```

2. **Environment Setup**
   ```bash
   cp .env.example .env
   # Update .env with your API configuration
   ```

3. **Start development**
   ```bash
   pnpm start
   ```

### Development Commands

```bash
# Development servers
pnpm start                      # Start Expo development server
pnpm run ios                    # Run on iOS simulator
pnpm run android               # Run on Android emulator
pnpm run web                    # Run on web browser

# Environment-specific builds
pnpm run start:staging          # Start with staging environment
pnpm run start:production       # Start with production environment
pnpm run prebuild:development   # Prebuild for development
pnpm run prebuild:production    # Prebuild for production

# Quality assurance
pnpm run lint                   # Run ESLint
pnpm run type-check            # Run TypeScript check
pnpm run test                   # Run Jest tests
pnpm run test:ci               # Run tests with coverage
pnpm run check-all             # Run all quality checks
pnpm run lint:translations     # Lint translation files

# E2E Testing
pnpm run install-maestro       # Install Maestro testing framework
pnpm run e2e-test              # Run end-to-end tests

# EAS Builds
pnpm run build:development:ios    # Development build for iOS
pnpm run build:development:android # Development build for Android
pnpm run build:staging:ios        # Staging build for iOS
pnpm run build:staging:android    # Staging build for Android
pnpm run build:production:ios     # Production build for iOS
pnpm run build:production:android # Production build for Android

# Release management
pnpm run app-release           # Create new app version
```

## 📱 API Integration

This mobile app integrates with the CheckRight Laravel API located in `../api/`.

### Authentication Flow

1. **Laravel Sanctum Integration**: Uses Sanctum tokens for API authentication
2. **Multi-tenant Support**: Automatically handles tenant context
3. **Secure Storage**: Tokens stored securely using Expo SecureStore

### Environment Configuration

Update your `.env` file with API settings:

```env
# API Configuration
API_URL=http://localhost:8000  # For local development
# API_URL=https://api.checkright.com  # For production

# Mobile specific settings
APP_ENV=development
EXPO_ACCOUNT_OWNER=your-expo-username
EAS_PROJECT_ID=your-eas-project-id
```

### API Endpoints Used

- `POST /api/auth/login` - User authentication
- `POST /api/auth/logout` - User logout
- `GET /api/user` - Get authenticated user
- Additional endpoints documented in API README

## 🏗️ Architecture

### Tech Stack

- **Framework**: Expo (React Native)
- **Navigation**: Expo Router (file-based routing)
- **Styling**: NativeWind (Tailwind CSS for React Native)
- **State Management**: Zustand
- **Data Fetching**: React Query with custom query kit
- **Forms**: React Hook Form with Zod validation
- **Authentication**: Expo SecureStore + Laravel Sanctum
- **Internationalization**: i18next
- **Storage**: React Native MMKV
- **Testing**: Jest + React Native Testing Library + Maestro

### Project Structure

```
apps/mobile-app/
├── src/
│   ├── components/        # Reusable UI components
│   ├── screens/          # Screen components
│   ├── navigation/       # Navigation configuration
│   ├── services/         # API services and utilities
│   ├── stores/           # Zustand stores
│   ├── hooks/            # Custom React hooks
│   ├── types/            # TypeScript type definitions
│   └── utils/            # Utility functions
├── assets/               # Images, fonts, icons
├── .maestro/            # E2E test files
└── app/                 # Expo Router app directory
```

## Why Expo and not React Native CLI?

We have been using Expo as our main framework since the introduction of [Continuous Native Generation (CNG)](https://docs.expo.dev/workflow/continuous-native-generation/) concept and we are happy with the experience.

I think this question is not valid anymore, especially after the last React conference when the core React native team recommended using Expo for new projects.

> "As of today, the only recommended community framework for React Native is Expo. Folks at Expo have been investing in the React Native ecosystem since the early days of React Native and as of today, we believe the developer experience offered by Expo is best in class." React native core team

Still hesitating? Check out this [article](https://reactnative.dev/blog/2024/06/25/use-a-framework-to-build-react-native-apps) or this [video](https://www.youtube.com/watch?v=lifGTznLBcw), maybe this one [video](https://www.youtube.com/watch?v=ek_IdGC0G80) too.

## 💎 Libraries used

- [Expo](https://docs.expo.io/)
- [Expo Router](https://docs.expo.dev/router/introduction/)
- [Nativewind](https://www.nativewind.dev/v4/overview)
- [Flash list](https://github.com/Shopify/flash-list)
- [React Query](https://tanstack.com/query/v4)
- [Axios](https://axios-http.com/docs/intro)
- [React Hook Form](https://react-hook-form.com/)
- [i18next](https://www.i18next.com/)
- [zustand](https://github.com/pmndrs/zustand)
- [React Native MMKV](https://github.com/mrousavy/react-native-mmkv)
- [React Native Gesture Handler](https://docs.swmansion.com/react-native-gesture-handler/docs/)
- [React Native Reanimated](https://docs.swmansion.com/react-native-reanimated/docs/)
- [React Native Svg](https://github.com/software-mansion/react-native-svg)
- [React Error Boundaries](https://github.com/bvaughn/react-error-boundary)
- [Expo Image](https://docs.expo.dev/versions/unversioned/sdk/image/)
- [React Native Keyboard Controller](https://github.com/kirillzyusko/react-native-keyboard-controller)
- [Moti](https://moti.fyi/)
- [React Native Safe Area Context](https://github.com/th3rdwave/react-native-safe-area-context)
- [React Native Screens](https://github.com/software-mansion/react-native-screens)
- [Tailwind Variants](https://www.tailwind-variants.org/)
- [Zod](https://zod.dev/)

## Contributors

This starter is maintained by [CheckRight mobile tribe team](https://www.checkright.com/team) and we welcome new contributors to join us in improving it. If you are interested in getting involved in the project, please don't hesitate to open an issue or submit a pull request.

In addition to maintaining this starter kit, we are also available to work on custom projects and help you build your dream app. If you are looking for experienced and reliable developers to bring your app vision to life, please visit our website at [checkright.com/contact](https://www.checkright.com/contact) to get in touch with us. We would be happy to discuss your project in more detail and explore how we can help you achieve your goals.

## 🔥 How to contribute?

Thank you for your interest in contributing to our project. Your involvement is greatly appreciated and we welcome your contributions. Here are some ways you can help us improve this project:

1. Show your support for the project by giving it a 🌟 on Github. This helps us increase visibility and attract more contributors.
2. Share your thoughts and ideas with us by opening an issue. If you have any suggestions or feedback about any aspect of the project, we are always eager to hear from you and have a discussion.
3. If you have any questions about the project, please don't hesitate to ask. Simply open an issue and our team will do our best to provide a helpful and informative response.
4. If you encounter a bug or typo while using the starter kit or reading the documentation, we would be grateful if you could bring it to our attention. You can open an issue to report the issue, or even better, submit a pull request with a fix.

We value the input and contributions of our community and look forward to working with you to improve this project.

## ❓ FAQ

If you have any questions about the starter and want answers, please check out the [Discussions](https://github.com/checkright/react-native-template-checkright/discussions) page.

## 🔖 License

Private Repository - All Rights Reserved
