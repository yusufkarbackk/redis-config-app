<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TableResource\Pages;
use App\Models\Application;
use App\Models\ApplicationField;
use App\Models\DatabaseTable;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Str;

class TableResource extends Resource
{
    protected static ?string $model = DatabaseTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected ?bool $hasDatabaseTransactions = true;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Table Configurations')->schema([
                Select::make('database_config_id')
                    ->label('Database')
                    ->relationship('database', 'name')
                    ->required(),

                TextInput::make('table_name')
                    ->label('Table Name')
                    ->required(),

                Repeater::make('applicationSubscriptions')
                    ->label('Subscribed Applications')
                    ->relationship()
                    ->schema([
                        Select::make('application_id')
                            ->label('Application')
                            ->options(Application::all()->pluck('name', 'id'))
                            ->reactive()
                            ->required(),

                        TextInput::make('consumer_group')
                            ->label('Consumer Group')
                            ->default(fn() => 'group:' . Str::random(16))
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Repeater::make('fieldMappings')
                            ->label('Field Mappings')
                            ->relationship()
                            ->schema([
                                Select::make('application_field_id')
                                    ->label('App Field')
                                    ->options(function (Get $get) {
                                        $applicationId = $get('../../application_id'); // may need to adjust path
                                        if (!$applicationId)
                                            return [];

                                        return ApplicationField::where('application_id', $applicationId)
                                            ->pluck('name', 'id');
                                    })
                                    ->required(),

                                TextInput::make('mapped_to')
                                    ->label('Map To (Table Field)')
                                    ->required(),
                            ]),
                    ]),
            ]),
        ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('table_name'),
                TextColumn::make(name: 'application.name'),
                TextColumn::make(name: 'database.name'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTables::route('/'),
            'create' => Pages\CreateTable::route('/create'),
            'edit' => Pages\EditTable::route('/{record}/edit'),
        ];
    }
}
