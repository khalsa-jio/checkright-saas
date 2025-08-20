# 4.3. Frontend Architecture (React Native)
* **Organization:** A feature-based directory structure will be used (`src/features/auth`, `src/features/inspections`) with expo-router for file-based routing (`src/app/`).
* **State Management:** Zustand will be used for simple, performant global state, enhanced with React Query for sophisticated API state management.
* **Routing:** Expo Router will be used to manage file-based navigation with authentication and main application navigators.
* **Services:** A centralized API client using Axios with interceptors for auth tokens, enhanced with React Query for caching, synchronization, and background updates.
* **UI System:** NativeWind for Tailwind CSS styling, comprehensive component library with theming, animations, and accessibility support.
* **Storage:** React Native MMKV for high-performance storage and React Native Keychain for secure credential storage.
* **Development Tools:** Production-ready starter template with ESLint, Prettier, TypeScript, Jest, and React Native Testing Library.
