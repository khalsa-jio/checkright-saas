<?php

namespace Tests\Unit;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $superAdmin;

    protected User $admin;

    protected User $manager;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test company
        $this->company = Company::factory()->create();

        // Create users with different roles
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null, // Super admin doesn't belong to any tenant
            'role' => 'super-admin',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'admin',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'manager',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'operator',
        ]);
    }

    public function test_admin_can_create_users(): void
    {
        $this->actingAs($this->admin);

        $result = UserResource::canCreate();

        $this->assertTrue($result);
    }

    public function test_manager_can_create_users(): void
    {
        $this->actingAs($this->manager);

        $result = UserResource::canCreate();

        $this->assertTrue($result);
    }

    public function test_operator_cannot_create_users(): void
    {
        $this->actingAs($this->operator);

        $result = UserResource::canCreate();

        $this->assertFalse($result);
    }

    public function test_admin_can_edit_any_user(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(UserResource::canEdit($this->manager));
        $this->assertTrue(UserResource::canEdit($this->operator));
    }

    public function test_manager_can_edit_operators_only(): void
    {
        $this->actingAs($this->manager);

        $this->assertTrue(UserResource::canEdit($this->operator));
        $this->assertFalse(UserResource::canEdit($this->admin));
        $this->assertFalse(UserResource::canEdit($this->manager));
    }

    public function test_operator_cannot_edit_other_users(): void
    {
        $this->actingAs($this->operator);

        $this->assertFalse(UserResource::canEdit($this->admin));
        $this->assertFalse(UserResource::canEdit($this->manager));

        // Note: Even operators cannot edit other operators
        $anotherOperator = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'operator',
        ]);
        $this->assertFalse(UserResource::canEdit($anotherOperator));
    }

    public function test_admin_can_view_any_user(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(UserResource::canView($this->manager));
        $this->assertTrue(UserResource::canView($this->operator));
        $this->assertTrue(UserResource::canView($this->admin));
    }

    public function test_manager_can_view_non_admin_users(): void
    {
        $this->actingAs($this->manager);

        $this->assertTrue(UserResource::canView($this->operator));
        $this->assertTrue(UserResource::canView($this->manager));
        $this->assertFalse(UserResource::canView($this->admin));
    }

    public function test_users_can_view_themselves(): void
    {
        $this->actingAs($this->operator);
        $this->assertTrue(UserResource::canView($this->operator));

        $this->actingAs($this->manager);
        $this->assertTrue(UserResource::canView($this->manager));

        $this->actingAs($this->admin);
        $this->assertTrue(UserResource::canView($this->admin));
    }

    public function test_get_role_options_returns_all_roles_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        $reflection = new \ReflectionClass(UserResource::class);
        $method = $reflection->getMethod('getRoleOptions');
        $method->setAccessible(true);

        $options = $method->invoke(null);

        $this->assertEquals([
            'super-admin' => 'Super Admin',
            'admin' => 'Company Admin',
            'manager' => 'Manager',
            'operator' => 'Operator',
        ], $options);
    }

    public function test_get_role_options_returns_correct_roles_for_admin(): void
    {
        $this->actingAs($this->admin);

        $reflection = new \ReflectionClass(UserResource::class);
        $method = $reflection->getMethod('getRoleOptions');
        $method->setAccessible(true);

        $options = $method->invoke(null);

        $this->assertEquals([
            'admin' => 'Company Admin',
            'manager' => 'Manager',
            'operator' => 'Operator',
        ], $options);
    }

    public function test_get_role_options_returns_operator_only_for_manager(): void
    {
        $this->actingAs($this->manager);

        $reflection = new \ReflectionClass(UserResource::class);
        $method = $reflection->getMethod('getRoleOptions');
        $method->setAccessible(true);

        $options = $method->invoke(null);

        $this->assertEquals([
            'operator' => 'Operator',
        ], $options);
    }

    public function test_get_role_options_returns_empty_for_operator(): void
    {
        $this->actingAs($this->operator);

        $reflection = new \ReflectionClass(UserResource::class);
        $method = $reflection->getMethod('getRoleOptions');
        $method->setAccessible(true);

        $options = $method->invoke(null);

        $this->assertEmpty($options);
    }

    public function test_super_admin_permissions(): void
    {
        $this->actingAs($this->superAdmin);

        // Super admin should have access to the UserResource
        $this->assertTrue(UserResource::canAccess());

        // Super admin can create users
        $this->assertTrue(UserResource::canCreate());

        // Super admin can edit anyone
        $this->assertTrue(UserResource::canEdit($this->admin));
        $this->assertTrue(UserResource::canEdit($this->manager));
        $this->assertTrue(UserResource::canEdit($this->operator));

        // Super admin can view anyone
        $this->assertTrue(UserResource::canView($this->admin));
        $this->assertTrue(UserResource::canView($this->manager));
        $this->assertTrue(UserResource::canView($this->operator));

        // Super admin can delete anyone
        $this->assertTrue(UserResource::canDelete($this->admin));
        $this->assertTrue(UserResource::canDelete($this->manager));
        $this->assertTrue(UserResource::canDelete($this->operator));
    }

    public function test_tenant_scoping_with_super_admin(): void
    {
        // Create another company with users
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherCompany->id,
            'role' => 'operator',
        ]);

        $this->actingAs($this->superAdmin);

        // Super admin should see all users regardless of tenant
        $query = UserResource::getEloquentQuery();
        $userCount = $query->count();

        // Should see users from both companies plus super admin
        $this->assertGreaterThanOrEqual(5, $userCount); // superAdmin, admin, manager, operator, otherUser
    }

    public function test_delete_permissions_match_edit_permissions(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(UserResource::canDelete($this->manager));
        $this->assertTrue(UserResource::canDelete($this->operator));

        $this->actingAs($this->manager);
        $this->assertTrue(UserResource::canDelete($this->operator));
        $this->assertFalse(UserResource::canDelete($this->admin));

        $this->actingAs($this->operator);
        $this->assertFalse(UserResource::canDelete($this->admin));
        $this->assertFalse(UserResource::canDelete($this->manager));
    }
}
