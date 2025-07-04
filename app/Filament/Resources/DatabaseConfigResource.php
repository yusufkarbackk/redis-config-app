<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseConfigResource\Pages;
use App\Filament\Resources\DatabaseConfigResource\RelationManagers;
use App\Models\Application;
use App\Models\DatabaseConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\FormsComponent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DatabaseConfigResource extends Resource
{
    protected static ?string $model = DatabaseConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Database Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('connection_type')
                            ->options([
                                'mysql' => 'MySQL',
                                'pgsql' => 'PostgreSQL'
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('host')
                            ->required()
                            ->default('localhost'),
                        Forms\Components\TextInput::make('port')
                            ->numeric()
                            ->required()
                            ->default(function ($get) {
                                return $get('connection_type') === 'mysql' ? 3306 : 5432;
                            }),
                        Forms\Components\TextInput::make('database_name')
                            ->required(),
                        Forms\Components\TextInput::make('username')
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => $state ? encrypt($state) : null) // Atau gunakan encrypt jika tidak hash
                            ->default('')
                            ->dehydrated(fn($state) => filled($state)) // hanya update kalau field diisi
                            ->helperText('Kosongkan jika tidak ingin mengubah password')
                            ->autocomplete('new-password')
                    ])
                    ->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('connection_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('host'),
                Tables\Columns\TextColumn::make('database_name'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListDatabaseConfigs::route('/'),
            'create' => Pages\CreateDatabaseConfig::route('/create'),
            'edit' => Pages\EditDatabaseConfig::route('/{record}/edit'),
        ];
    }
}
