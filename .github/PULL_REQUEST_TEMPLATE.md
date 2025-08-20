# Pull Request Template

## Summary

Brief description of what this PR does.

## Type of Change

- [ ] 🐛 Bug fix
- [ ] ✨ New feature  
- [ ] 💥 Breaking change
- [ ] 📝 Documentation update
- [ ] 🎨 Code style/formatting
- [ ] ♻️ Refactoring
- [ ] ⚡ Performance improvement
- [ ] 🧪 Tests

## Applications Affected

- [ ] API (`apps/api/`)
- [ ] Mobile App (`apps/mobile-app/`)
- [ ] Documentation (`docs/`)
- [ ] GitHub Workflows (`.github/`)

## Testing

- [ ] API tests pass (`cd apps/api && php artisan test`)
- [ ] Mobile tests pass (`cd apps/mobile-app && pnpm run test`)
- [ ] Code style checks pass (`vendor/bin/pint` for API, `pnpm run lint` for mobile)
- [ ] Type checking passes (`pnpm run type-check` for mobile)
- [ ] Manual testing completed

## Mobile App Specific (if applicable)

- [ ] Tested on iOS simulator
- [ ] Tested on Android emulator
- [ ] API integration working correctly
- [ ] No breaking changes to API contract

## API Specific (if applicable)

- [ ] Database migrations reviewed
- [ ] Multi-tenancy considerations addressed
- [ ] Mobile endpoints maintain backward compatibility
- [ ] Queue jobs tested (if applicable)

## Security Considerations

- [ ] No sensitive data exposed in logs
- [ ] Input validation implemented
- [ ] Authentication/authorization properly implemented
- [ ] CORS configuration reviewed (for API changes)

## Checklist

- [ ] Self-review completed
- [ ] Documentation updated (if needed)
- [ ] Breaking changes documented
- [ ] Ready for review
