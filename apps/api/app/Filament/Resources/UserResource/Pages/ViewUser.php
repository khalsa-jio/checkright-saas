<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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
                Section::make('User Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('role')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'admin' => 'success',
                                'manager' => 'warning',
                                'operator' => 'primary',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        IconEntry::make('make_password_change')
                            ->label('Must Change Password')
                            ->boolean(),
                    ])
                    ->columns(2),
                Section::make('Account Status')
                    ->schema([
                        TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('Never'),
                        TextEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('Not verified'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('deleted_at')
                            ->label('Deactivated')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('Active'),
                    ])
                    ->columns(2),
                Section::make('Company Information')
                    ->schema([
                        TextEntry::make('company.name')
                            ->label('Company'),
                        TextEntry::make('company.domain')
                            ->label('Domain'),
                    ])
                    ->columns(2),
            ]);
    }
}
