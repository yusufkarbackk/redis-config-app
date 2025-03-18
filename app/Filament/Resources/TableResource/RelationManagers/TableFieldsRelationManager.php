<?php

namespace App\Filament\Resources\TableResource\RelationManagers;

use App\Models\ApplicationField;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TableFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'tableFields';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('field_name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('field_type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'date' => 'Date',
                    ])
                    ->required(),
                // Select the Application ID from database_tables
                Hidden::make('application_id')
                    ->default(fn(RelationManager $livewire) => $livewire->ownerRecord->application_id ?? null)
                    ->dehydrated(),
                // Select Application Field based on the selected Application ID
                Select::make('application_field_id')
                    ->label('Application Field')
                    ->options(function (RelationManager $livewire) {
                        $application_id = $livewire->ownerRecord->application_id;
                        return $application_id ? ApplicationField::where('application_id', $application_id)->pluck('name', 'id') : [];
                    })
                    ->required()
                    ->reactive()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_name')
            ->columns([
                TextColumn::make('field_name'),
                TextColumn::make('field_type'),
                TextColumn::make(name: 'application.name'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
