<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationResource\Pages\CreateInvitation;
use App\Filament\Resources\InvitationResource\Pages\EditInvitation;
use App\Filament\Resources\InvitationResource\Pages\ListInvitations;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isManager());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invitation Details')
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Company')
                            ->options(function () {
                                $user = auth()->user();
                                if ($user && $user->isSuperAdmin()) {
                                    return Company::pluck('name', 'id')->toArray();
                                }

                                return [];
                            })
                            ->searchable()
                            ->required()
                            ->visible(function () {
                                $user = auth()->user();

                                return $user && $user->isSuperAdmin();
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                                // Real-time validation when tenant changes
                                $email = $get('email');
                                if ($email && $state) {
                                    // Get current record ID if editing
                                    $currentInvitationId = null;
                                    if (isset($livewire->record)) {
                                        $currentInvitationId = $livewire->record->id;
                                    }

                                    // Check for existing user
                                    if (User::where('email', $email)->where('tenant_id', $state)->exists()) {
                                        $set('tenant_id', null); // Clear the field
                                        \Filament\Notifications\Notification::make()
                                            ->title('Email Already Exists')
                                            ->body('A user with this email already exists in the selected organization.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    // Check for existing invitation
                                    $existingInvitation = Invitation::where('email', $email)
                                        ->where('tenant_id', $state)
                                        ->when($currentInvitationId, function ($query) use ($currentInvitationId) {
                                            return $query->where('id', '!=', $currentInvitationId);
                                        })
                                        ->first();

                                    if ($existingInvitation) {
                                        $set('tenant_id', null); // Clear the field
                                        \Filament\Notifications\Notification::make()
                                            ->title('Invitation Already Exists')
                                            ->body('An invitation for this email already exists in the selected organization.')
                                            ->danger()
                                            ->send();
                                    }
                                }
                            })
                            ->placeholder('Select a company')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $formData = request()->all();
                                        $email = $formData['email'] ?? null;

                                        if ($email && $value) {
                                            // Check for existing user
                                            if (User::where('email', $email)->where('tenant_id', $value)->exists()) {
                                                $fail('A user with this email already exists in the selected organization.');
                                            }

                                            // Check for existing invitation
                                            $currentInvitationId = request()->route('record');
                                            $existingInvitation = Invitation::where('email', $email)
                                                ->where('tenant_id', $value)
                                                ->when($currentInvitationId, function ($query) use ($currentInvitationId) {
                                                    return $query->where('id', '!=', $currentInvitationId);
                                                })
                                                ->first();

                                            if ($existingInvitation) {
                                                $fail('An invitation for this email already exists in the selected organization.');
                                            }
                                        }
                                    };
                                },
                            ]),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                                // Real-time validation on email change
                                if ($state) {
                                    $user = auth()->user();
                                    $tenantId = $user->tenant_id;

                                    // For super admin, get tenant_id from form data
                                    if ($user && $user->isSuperAdmin()) {
                                        $tenantId = $get('tenant_id');
                                    }

                                    if ($tenantId) {
                                        // Get current record ID if editing
                                        $currentInvitationId = null;
                                        if (isset($livewire->record)) {
                                            $currentInvitationId = $livewire->record->id;
                                        }

                                        // Check for existing user
                                        if (User::where('email', $state)->where('tenant_id', $tenantId)->exists()) {
                                            $set('email', ''); // Clear the field
                                            \Filament\Notifications\Notification::make()
                                                ->title('Email Already Exists')
                                                ->body('A user with this email already exists in the selected organization.')
                                                ->danger()
                                                ->send();
                                        }

                                        // Check for existing invitation
                                        $existingInvitation = Invitation::where('email', $state)
                                            ->where('tenant_id', $tenantId)
                                            ->when($currentInvitationId, function ($query) use ($currentInvitationId) {
                                                return $query->where('id', '!=', $currentInvitationId);
                                            })
                                            ->first();

                                        if ($existingInvitation) {
                                            $set('email', ''); // Clear the field
                                            \Filament\Notifications\Notification::make()
                                                ->title('Invitation Already Exists')
                                                ->body('An invitation for this email already exists in the selected organization.')
                                                ->danger()
                                                ->send();
                                        }
                                    }
                                }
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $user = auth()->user();
                                        $tenantId = $user->tenant_id;

                                        // For super admin, get tenant_id from form data
                                        if ($user && $user->isSuperAdmin()) {
                                            $formData = request()->all();
                                            $tenantId = $formData['tenant_id'] ?? null;
                                        }

                                        if ($tenantId) {
                                            // Check for existing user
                                            if (User::where('email', $value)->where('tenant_id', $tenantId)->exists()) {
                                                $fail('A user with this email already exists in the selected organization.');
                                            }

                                            // Check for existing invitation (only for create or when tenant_id changes)
                                            $currentInvitationId = request()->route('record');
                                            $existingInvitation = Invitation::where('email', $value)
                                                ->where('tenant_id', $tenantId)
                                                ->when($currentInvitationId, function ($query) use ($currentInvitationId) {
                                                    return $query->where('id', '!=', $currentInvitationId);
                                                })
                                                ->first();

                                            if ($existingInvitation) {
                                                $fail('An invitation for this email already exists in the selected organization.');
                                            }
                                        }
                                    };
                                },
                            ]),
                        Select::make('role')
                            ->options(function () {
                                return self::getRoleOptionsForInvitation();
                            })
                            ->required(),
                        DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable()
                    ->visible(function () {
                        $user = auth()->user();

                        return $user && $user->isSuperAdmin();
                    }),
                TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'success' => 'admin',
                        'warning' => 'manager',
                        'primary' => 'operator',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Company Admin',
                        'manager' => 'Manager',
                        'operator' => 'Operator',
                        default => ucfirst($state)
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($record) {
                        if ($record->isAccepted()) {
                            return 'Accepted';
                        }
                        if ($record->userAlreadyExists()) {
                            return 'User Already Exists';
                        }
                        if ($record->isExpired()) {
                            return 'Expired';
                        }

                        return 'Pending';
                    })
                    ->colors([
                        'success' => 'Accepted',
                        'warning' => 'Pending',
                        'danger' => 'Expired',
                        'gray' => 'User Already Exists',
                    ]),
                TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Invited At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('accepted_at')
                    ->label('Accepted At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Company Admin',
                        'manager' => 'Manager',
                        'operator' => 'Operator',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'expired' => 'Expired',
                        'user_exists' => 'User Already Exists',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query->pending(),
                            'accepted' => $query->whereNotNull('accepted_at'),
                            'expired' => $query->expired(),
                            'user_exists' => $query->userExists(),
                            default => $query,
                        };
                    }),
                TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                EditAction::make()
                    ->visible(function ($record) {
                        // Hide edit action for accepted invitations
                        return ! $record->isAccepted();
                    }),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(function ($record) {
                        // Only show resend for valid invitations (not expired, not accepted, user doesn't exist)
                        return $record->isValid() && ! $record->userAlreadyExists();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Resend Invitation')
                    ->modalDescription('Are you sure you want to resend this invitation? A new email will be sent to the recipient.')
                    ->action(function (Invitation $record) {
                        $protocol = request()->isSecure() ? 'https' : 'http';

                        // Use the company's domain for the invitation URL
                        $company = $record->company;
                        if ($company && $company->domain) {
                            $domain = $company->domain . config('tenant.domain.suffix');
                        } else {
                            // Fallback to central domain
                            $domain = config('tenancy.central_domains')[0] ?? request()->getHost();
                        }

                        $acceptUrl = "{$protocol}://{$domain}/invitation/{$record->token}";
                        SendInvitationEmailJob::dispatch($record, $acceptUrl);
                        Notification::make()
                            ->title('Invitation Resent')
                            ->body("Invitation resent to {$record->email}")
                            ->success()
                            ->send();
                    }),
                Action::make('view_existing_user')
                    ->label('View Existing User')
                    ->icon('heroicon-o-user')
                    ->visible(fn ($record) => $record->userAlreadyExists())
                    ->url(function (Invitation $record) {
                        $existingUser = $record->existingUser();
                        if ($existingUser) {
                            return route('filament.admin.resources.users.view', ['record' => $existingUser->id]);
                        }
                    })
                    ->openUrlInNewTab(false),
                Action::make('view_invitation_url')
                    ->label('View Invitation URL')
                    ->icon('heroicon-o-link')
                    ->visible(function ($record) {
                        $user = auth()->user();

                        // Only show for super admins and for valid invitations (not expired, not accepted, user doesn't exist)
                        $isSuperAdmin = $user?->isSuperAdmin();
                        $isValidInvitation = $record && ! $record->isExpired() && ! $record->isAccepted() && ! $record->userAlreadyExists();

                        return $isSuperAdmin && $isValidInvitation;
                    })
                    ->modalHeading('Invitation URL')
                    ->modalContent(function (Invitation $record): \Illuminate\View\View {
                        $url = $record->getAcceptanceUrl();

                        return view('filament.modals.copy-invitation-url', compact('url'));
                    })
                    ->modalSubmitActionLabel('Done')
                    ->modalWidth('lg')
                    ->action(function () {
                        // No action needed - just close the modal
                    }),
                DeleteAction::make()
                    ->visible(function ($record) {
                        // Hide delete action for accepted invitations
                        return ! $record->isAccepted();
                    }),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferFilters(false);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
            'create' => CreateInvitation::route('/create'),
            'edit' => EditInvitation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = auth()->user();

        // Apply tenant scoping
        if ($user && ($user->isSuperAdmin())) {
            // super admin sees all invitations - no additional filtering needed
        } elseif ($user && ($user->isAdmin() || $user->isManager())) {
            $query = $query->where('tenant_id', $user->tenant_id);
        } else {
            return $query->whereRaw('1 = 0');
        }

        // Default to showing only pending invitations on index for non-super admins
        // But allow access to all invitations for edit pages and super admins
        if (request()->routeIs('filament.admin.resources.invitations.edit')) {
            return $query; // No additional filtering for edit pages
        }

        // Super admins can see all invitations, others only see pending
        if ($user && $user->isSuperAdmin()) {
            return $query; // Show all invitations for super admins
        }

        return $query->pending();
    }

    /**
     * Get role options for invitations based on current user's permissions.
     * Excludes super-admin as they cannot be invited.
     */
    private static function getRoleOptionsForInvitation(): array
    {
        $currentUser = auth()->user();

        if (! $currentUser) {
            return [];
        }

        // Super Admin can invite all roles except super-admin
        if ($currentUser->isSuperAdmin()) {
            return [
                'admin' => 'Company Admin',
                'manager' => 'Manager',
                'operator' => 'Operator',
            ];
        }

        // Company Admin can invite all roles except super-admin
        if ($currentUser->isAdmin()) {
            return [
                'admin' => 'Company Admin',
                'manager' => 'Manager',
                'operator' => 'Operator',
            ];
        }

        // Manager can only invite operators
        if ($currentUser->isManager()) {
            return [
                'operator' => 'Operator',
            ];
        }

        // Operators cannot invite
        return [];
    }
}
