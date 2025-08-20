# Laravel Multi-Tenancy Implementation Analysis

## Overview

This document analyzes two different approaches to multi-tenancy in Laravel applications with Filament:

1. **Project A (SaaS AI 2)**: Uses **Stancl Tenancy** package for database-level multi-tenancy
2. **Project B (TransitDash)**: Uses **Filament's built-in multi-tenancy** for application-level tenancy

## Architecture Comparison

### Stancl Tenancy Approach (SaaS AI 2)

**Database Architecture:**
- **Central Database**: Stores tenant information, domains, and global data
- **Tenant Databases**: Separate database per tenant (isolated data)
- **Domain-based identification**: Each tenant has own subdomain/domain

**Key Components:**
```php
// Tenant Model
class Company extends \Stancl\Tenancy\Database\Models\Tenant
{
    protected $fillable = ['name', 'subdomain'];
}

// Domain Model
class Domain extends \Stancl\Tenancy\Database\Models\Domain
{
    // Maps domains to tenants
}

// Tenancy Configuration
'central_domains' => [
    '127.0.0.1',
    'localhost',
    'checkright.test',
    '192.168.1.39:8000',
],

// Middleware Stack
->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    AuthenticateSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
    SubstituteBindings::class,
    DisableBladeIconComponents::class,
    DispatchServingFilamentEvent::class,
    InitializeTenancyByDomain::class, // Key middleware
])
```

**Advantages:**
- Complete data isolation (separate databases)
- Scalable for large number of tenants
- Security through physical separation
- Each tenant can have different schema versions

**Challenges:**
- Complex middleware order requirements
- Database migration complexity
- Backup/maintenance overhead per tenant
- Resource intensive for many tenants

### Filament Built-in Tenancy Approach (TransitDash)

**Database Architecture:**
- **Single Database**: All tenant data in same database
- **Tenant Scoping**: Data filtered by `organisation_id`
- **URL-based identification**: `/dashboard/{tenant}/...`

**Key Components:**
```php
// Tenant Model (Organisation)
class Organisation extends Model
{
    protected $fillable = ['name', 'brand_primary_colour', ...];
    
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

// User Model with Tenant Interface
class User extends Authenticatable implements FilamentUser, HasTenants
{
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return Organisation::all();
        } else {
            return new Collection([$this->organisation()->getResults()]);
        }
    }
    
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        } else {
            return $this->getOrganisationID() === $tenant->getKey();
        }
    }
}

// Panel Configuration
->tenant(Organisation::class)

// Resource Query Scoping
public static function getEloquentQuery(): Builder
{
    return Marker::where(function (Builder $query) {
        $query
            ->where('organisation_id', null)
            ->orWhere('organisation_id', Filament::getTenant()->id);
    });
}

// Automatic Tenant Assignment
$poster = Poster::create([
    'name' => 'untitled poster',
    'organisation_id' => Filament::getTenant()->getKey(),
]);
```

**Advantages:**
- Simpler setup and maintenance
- Built-in Filament integration
- Single database management
- Easier data relationships across tenants
- Less middleware complexity

**Challenges:**
- Shared database (data leakage risks)
- Query performance with large datasets
- Manual tenant scoping required
- Limited scalability for massive tenant counts

## Implementation Patterns

### Tenant Context Access

**Stancl Tenancy:**
```php
// Get current tenant
$currentTenant = tenant();

// Manual initialization
tenancy()->initialize($tenant);

// Domain lookup
$domain = Domain::where('domain', $currentDomain)->first();
```

**Filament Tenancy:**
```php
// Get current tenant in resources
Filament::getTenant()

// In tenant-aware queries
->where('organisation_id', Filament::getTenant()->id)

// User's accessible tenants
auth()->user()->getTenants($panel)
```

### Resource Scoping

**Stancl Tenancy:**
```php
// Automatic through middleware
// All queries automatically scoped to tenant database

// Explicit tenant assignment
$data['tenant_id'] = $currentTenant->id;
```

**Filament Tenancy:**
```php
// Manual scoping in getEloquentQuery()
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('organisation_id', Filament::getTenant()->id);
}

// Automatic assignment in creation
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['organisation_id'] = Filament::getTenant()->id;
    return $data;
}
```

### Middleware Configuration

**Stancl Tenancy:**
```php
// Routes need specific middleware
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])

// Filament panel middleware
->middleware([
    // Standard Laravel middleware
    InitializeTenancyByDomain::class, // Last in stack
])
```

**Filament Tenancy:**
```php
// Standard middleware stack
->middleware([
    Settings::class, // Custom tenant context middleware
    EncryptCookies::class,
    // ... standard middleware
])

// Custom middleware for URL parameter handling
class Settings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (count($request->segments()) >= 3 && $request->segment(3) === 'settings') {
            $request->route()->setParameter('record', $request->segment(2));
        }
        return $next($request);
    }
}
```

## Best Practices Learned

### From Stancl Tenancy Implementation:

1. **Middleware Order is Critical**: Tenancy middleware must run before database queries
2. **Fallback Strategies**: Always have manual tenant lookup as backup
3. **Central vs Tenant Domain Handling**: Clear separation of concerns
4. **Early Lifecycle Issues**: Avoid tenant context access in constructors/early methods

### From Filament Tenancy Implementation:

1. **Consistent Scoping**: Apply tenant scoping in `getEloquentQuery()` for all resources
2. **Automatic Assignment**: Use `Filament::getTenant()` for new record tenant assignment
3. **Permission Patterns**: Clear role-based access with tenant awareness
4. **URL Structure**: Clean tenant-based routing (`/dashboard/{tenant}/...`)

## Migration Considerations

### From Filament to Stancl Tenancy:

**Pros:**
- Better data isolation
- More scalable architecture
- Industry-standard approach

**Cons:**
- Significant refactoring required
- Database migration complexity
- Middleware stack reconfiguration

### From Stancl to Filament Tenancy:

**Pros:**
- Simpler maintenance
- Better Filament integration
- Single database management

**Cons:**
- Data consolidation required
- Security considerations
- Query performance optimization needed

## Recommendations

### Choose Stancl Tenancy When:
- Enterprise SaaS with many tenants (100+)
- Strong data isolation requirements
- Compliance/regulatory requirements
- Different schema needs per tenant
- High security requirements

### Choose Filament Tenancy When:
- Small to medium tenant count (<100)
- Rapid development timeline
- Simple tenant requirements
- Team familiar with Filament
- Budget/resource constraints

## Security Considerations

### Stancl Tenancy Security:
- Physical database separation
- Domain-based access control
- Middleware-enforced isolation
- Risk: Middleware bypass vulnerabilities

### Filament Tenancy Security:
- Query-level scoping required everywhere
- Risk: Missing tenant filters in queries
- Risk: Cross-tenant data leakage
- Benefit: Centralized audit logging

## Conclusion

Both approaches have their merits. **Stancl Tenancy** provides enterprise-grade isolation but with complexity overhead. **Filament Tenancy** offers rapid development and maintenance ease but requires careful query scoping.

The current SaaS AI 2 project uses Stancl Tenancy appropriately for its multi-tenant SaaS requirements, while TransitDash's use of Filament tenancy suits its simpler organizational separation needs.

## Current Issue Resolution

The tenant context initialization issue in SaaS AI 2 was resolved by understanding that:

1. Middleware order matters in Laravel 11
2. Resource queries can run before middleware completion
3. Fallback tenant identification strategies are essential
4. Debug middleware helped identify the exact execution flow

The solution involved reverting complex middleware priority changes and keeping the simpler tenant validation approach that works with the existing middleware stack.