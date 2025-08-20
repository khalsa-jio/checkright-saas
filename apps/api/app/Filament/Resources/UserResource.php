<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Allow access for super-admin, admin, and manager
        return $user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isManager());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('role')
                            ->options(fn () => self::getRoleOptions())
                            ->required(),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->rules(['required', 'min:8'])
                            ->maxLength(255),
                        Toggle::make('must_change_password')
                            ->label('Must Change Password on Next Login'),
                    ])
                    ->columns(2),
                Section::make('Status Information')
                    ->schema([
                        DateTimePicker::make('last_login_at')
                            ->label('Last Login')
                            ->disabled()
                            ->displayFormat('Y-m-d H:i:s'),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email Verified')
                            ->disabled()
                            ->displayFormat('Y-m-d H:i:s'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
                Section::make('Social Accounts')
                    ->schema([
                        Repeater::make('socialAccounts')
                            ->relationship('socialAccounts')
                            ->schema([
                                Select::make('provider')
                                    ->options([
                                        'google' => 'Google',
                                        'facebook' => 'Facebook',
                                        'instagram' => 'Instagram',
                                    ])
                                    ->required()
                                    ->disabled(),
                                TextInput::make('provider_id')
                                    ->label('Provider ID')
                                    ->disabled(),
                                TextInput::make('name')
                                    ->label('Provider Name')
                                    ->disabled(),
                                TextInput::make('email')
                                    ->label('Provider Email')
                                    ->disabled(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['provider'] ?? null ? ucfirst($state['provider']) . ' Account' : null)
                            ->addActionLabel('Link Social Account')
                            ->deletable(true)
                            ->reorderable(false),
                    ])
                    ->hiddenOn('create')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'danger' => 'super-admin',
                        'success' => 'admin',
                        'warning' => 'manager',
                        'primary' => 'operator',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super-admin' => 'Super Admin',
                        'admin' => 'Company Admin',
                        'manager' => 'Manager',
                        'operator' => 'Operator',
                        default => ucfirst($state)
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->trashed() ? 'Deactivated' : 'Active')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Deactivated',
                    ]),
                IconColumn::make('must_change_password')
                    ->label('Must Change PWD')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deactivated')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('Active')
                    ->color(fn ($record) => $record->trashed() ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'super-admin' => 'Super Admin',
                        'admin' => 'Company Admin',
                        'manager' => 'Manager',
                        'operator' => 'Operator',
                    ]),
                TernaryFilter::make('must_change_password')
                    ->label('Must Change Password'),
                TrashedFilter::make()
                    ->label('User Status')
                    ->placeholder('All Users (Active & Deactivated)')
                    ->trueLabel('Active Users Only')
                    ->falseLabel('Deactivated Users Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('deleted_at'),
                        false: fn (Builder $query) => $query->whereNotNull('deleted_at'),
                        blank: fn (Builder $query) => $query, // Show all users by default
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('deactivate')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->delete())
                    ->visible(fn ($record) => ! $record->trashed()),
                Action::make('reactivate')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->restore())
                    ->visible(fn ($record) => $record->trashed()),
                Action::make('force_password_reset')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['must_change_password' => true]))
                    ->visible(fn ($record) => ! $record->must_change_password),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Deactivate Selected'),
                    RestoreBulkAction::make()
                        ->label('Reactivate Selected'),
                    ForceDeleteBulkAction::make(),
                    BulkAction::make('force_password_reset')
                        ->label('Force Password Reset')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn (User $record) => $record->update(['must_change_password' => true])
                            );
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferFilters(false)
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = auth()->user();

        // Super admin can see all users
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        // Company admins and managers can only see users from their own tenant
        if ($user && ($user->isAdmin() || $user->isManager())) {
            return $query->where('tenant_id', $user->tenant_id);
        }

        // Operators shouldn't access this at all (blocked by middleware)
        // but if they somehow do, they can only see themselves
        if ($user && $user->isOperator()) {
            return $query->where('id', $user->id);
        }

        // Fallback: no access
        return $query->whereRaw('1 = 0');
    }

    /**
     * Get role options based on current user's permissions.
     */
    private static function getRoleOptions(): array
    {
        $currentUser = auth()->user();

        if (! $currentUser) {
            return [];
        }

        // Super Admin can assign all roles including super-admin
        if ($currentUser->isSuperAdmin()) {
            return [
                'super-admin' => 'Super Admin',
                'admin' => 'Company Admin',
                'manager' => 'Manager',
                'operator' => 'Operator',
            ];
        }

        // Company Admin can assign all roles except super-admin
        if ($currentUser->isAdmin()) {
            return [
                'admin' => 'Company Admin',
                'manager' => 'Manager',
                'operator' => 'Operator',
            ];
        }

        // Manager can only assign Operator role
        if ($currentUser->isManager()) {
            return [
                'operator' => 'Operator',
            ];
        }

        // Operators cannot assign roles
        return [];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isManager());
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Super admin can edit anyone
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can edit anyone in their tenant (except super-admin)
        if ($user->isAdmin() && ! $record->isSuperAdmin()) {
            return true;
        }

        // Manager can edit operators only in their tenant
        if ($user->isManager() && $record->isOperator() && $record->tenant_id === $user->tenant_id) {
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        return self::canEdit($record);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Super admin can view anyone
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Users can view others based on hierarchy within same tenant
        if ($user->tenant_id === $record->tenant_id) {
            return $user->isAdmin() ||
                   ($user->isManager() && ! $record->isAdmin()) ||
                   $user->id === $record->id;
        }

        return false;
    }
}
