<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DatabaseConfigResource\Pages;
use App\Filament\Resources\DatabaseConfigResource\RelationManagers;
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
                                'postgres' => 'PostgreSQL'
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
                            ->password()
                            ->default('')
                            ->dehydrated(true),
                        Forms\Components\TextInput::make('consumer_group')
                            ->default(fn() => 'group:' . Str::random(16))
                            ->disabled()
                            ->dehydrated(true)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Field Subscripition')
                    ->schema([
                        Forms\Components\CheckboxList::make('fields')
                            ->relationship('fields', 'name')
                            ->options(function () {
                                return \App\Models\ApplicationField::query()
                                    ->with('application')
                                    ->get()
                                    ->mapWithKeys(function ($field) {
                                        return [
                                            $field->id => "{$field->application->name} - {$field->name} ({$field->data_type})"
                                        ];
                                    });
                            })
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable()

                    ])
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
                Tables\Columns\TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Subscribed Fields'),
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
