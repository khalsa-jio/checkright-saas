# 5.1. Unified Project Structure (Monorepo)
A monorepo will be used to house both the Laravel and React Native applications.
```plaintext
/
├── apps/
│   ├── api/          # Laravel + Filament Application
│   └── mobile-app/   # React Native Application (Production starter template)
├── packages/
│   └── shared/       # Shared TypeScript types and validation rules
└── package.json      # Root package manager
