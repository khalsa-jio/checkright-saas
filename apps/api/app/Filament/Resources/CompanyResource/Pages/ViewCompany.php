<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Company Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Company Name'),

                        TextEntry::make('domain')
                            ->label('Domain')
                            ->suffix('.checkright.test'),

                        TextEntry::make('id')
                            ->label('Tenant ID')
                            ->copyable(),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Users')
                    ->schema([
                        TextEntry::make('users_count')
                            ->label('Total Users')
                            ->getStateUsing(fn ($record) => $record->users()->count()),

                        RepeatableEntry::make('users')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Name'),
                                TextEntry::make('email')
                                    ->label('Email'),
                                TextEntry::make('role')
                                    ->label('Role')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'admin' => 'success',
                                        'manager' => 'warning',
                                        'operator' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('created_at')
                                    ->label('Joined')
                                    ->dateTime(),
                            ])
                            ->columns(4)
                            ->visible(fn ($record) => $record->users()->exists()),
                    ]),

                Section::make('Pending Invitations')
                    ->schema([
                        RepeatableEntry::make('invitations')
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Email'),
                                TextEntry::make('role')
                                    ->label('Role')
                                    ->badge(),
                                TextEntry::make('expires_at')
                                    ->label('Expires')
                                    ->dateTime()
                                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success'),
                                TextEntry::make('created_at')
                                    ->label('Sent')
                                    ->dateTime(),
                            ])
                            ->columns(4)
                            ->visible(fn ($record) => $record->invitations()->whereNull('accepted_at')->exists()),
                    ]),
            ]);
    }
}
