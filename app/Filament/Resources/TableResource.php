<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TableResource\Pages;
use App\Filament\Resources\TableResource\RelationManagers;
use App\Models\Application;
use App\Models\ApplicationField;
use App\Models\DatabaseConfig;
use App\Models\DatabaseTable;
use App\Models\Table as TableModel;
use Faker\Provider\ar_EG\Text;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class TableResource extends Resource
{
    protected static ?string $model = DatabaseTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected ?bool $hasDatabaseTransactions = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Table Configurations')
                    ->schema([
                        Select::make('database_config_id')
                            ->label('Database')
                            ->relationship('database', 'name')
                            ->required(),
                        TextInput::make('table_name')
                            ->label('Table Name'),

                        Select::make('application_id')
                            ->label('Application')
                            ->relationship('application', 'name')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                // Clear application_field_id when application_id changes
                                $set('fields.*.application_field_id', null);
                            }),

                        // Repeater::make('table_fields')
                        //     ->label('Fields')
                        //     ->relationship('tableFields')
                        //     ->schema([
                        //         TextInput::make('field_name')
                        //             ->label('Field Name'),

                        //         Select::make('field_type')
                        //             ->options([
                        //                 'string' => 'String',
                        //                 'integer' => 'Integer',
                        //                 'text' => 'Text',
                        //                 'boolean' => 'Boolean',
                        //                 'date' => 'Date',
                        //                 'datetime' => 'DateTime',
                        //                 'time' => 'Time',
                        //                 'json' => 'JSON',
                        //             ]),
                        //         Select::make('application_field_id')
                        //             ->label('Application Field')
                        //             ->options(function (callable $get) {
                        //                 $appliation_id = $get('../../application_id');
                        //                 return $appliation_id ? ApplicationField::where('application_id', $appliation_id)->get()->pluck('name', 'id') : [];
                        //             })
                        //             ->reactive(),
                        //         Hidden::make('application_id')
                        //             ->default(fn(callable $get) => $get('../../application_id'))
                        //             ->dehydrated(true), // Ensure it gets stored in the database
                        //         Hidden::make('table_id') // Menyimpan ID tabel yang sedang dibuat
                        //             ->default(fn(callable $get): mixed => $get('../../id')) // Ambil ID tabel dari parent
                        //             ->dehydrated(true),
                        //     ])
                        //     ->defaultItems(1)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                Tables\Columns\TextColumn::make('table_name'),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\TableFieldsRelationManager::class
        ];
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
