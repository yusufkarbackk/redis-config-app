<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationResource\Pages;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Rules\UniqueFieldNamesRule;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Generate unique API key with collision handling
     */
    private static function generateUniqueApiKey(): string
    {
        $maxRetries = 5;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            $apiKey = Str::random(32);

            // Check for collision
            if (!Application::where('api_key', $apiKey)->exists()) {
                return $apiKey;
            }

            $retryCount++;
            Log::warning('API key collision detected, retrying', [
                'retry_count' => $retryCount,
                'generated_key_prefix' => substr($apiKey, 0, 8)
            ]);
        }

        // If we reach here, something is wrong
        throw new Exception('Failed to generate unique API key after multiple attempts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'An application with this name already exists.',
                        'required' => 'Application name is required.',
                        'max' => 'Application name must not exceed 255 characters.'
                    ]),
                Forms\Components\TextInput::make('description')
                    ->maxLength(65535)
                    ->validationMessages([
                        'max' => 'Description must not exceed 65535 characters.'
                    ]),
                Forms\Components\TextInput::make('api_key')
                    ->dehydrated(true)
                    ->disabled()
                    ->default(fn() => self::generateUniqueApiKey())
                    ->helperText('API key is automatically generated and unique.'),
                Forms\Components\Repeater::make('fields')
                    ->relationship('applicationFields')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Field name is required.',
                                'max' => 'Field name must not exceed 255 characters.'
                            ]),
                        Forms\Components\Select::make('data_type')
                            ->options([
                                'string' => 'String',
                                'number' => 'Number',
                                'boolean' => 'Boolean',
                                'json' => 'JSON'
                            ])
                            ->required()
                            ->validationMessages([
                                'required' => 'Data type is required.'
                            ])
                    ])
                    ->columnSpanFull()
                    ->defaultItems(1) // Require at least one field by default
                    ->addActionLabel('Add Field')
                    ->minItems(1) // Require at least one field
                    ->validationMessages([
                        'fields.*.name.required' => 'Each field must have a name.',
                        'fields.*.name.max' => 'Field names must not exceed 255 characters.',
                        'fields.*.data_type.required' => 'Each field must have a data type.',
                        'fields.min_items' => 'At least one field is required for the application.'
                    ])
                    ->rule(new UniqueFieldNamesRule())
                    ->helperText('Add at least one field to define the data structure for your application.')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('api_key'),
                TextColumn::make('created_at')
                    ->dateTime()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->before(function (Tables\Actions\EditAction $action, $record) {
                        try {
                            Log::info('Application edit action initiated', [
                                'application_id' => $record->id,
                                'application_name' => $record->name
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error in Application edit action before hook', [
                                'application_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                        }
                    })
                    ->successNotificationTitle('Application updated successfully')
                    ->failureNotificationTitle('Failed to update application')
                    ->failureNotification(function ($exception) {
                        Log::error('Application edit action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return 'An error occurred while updating the application. Please try again.';
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, $record) {
                        try {
                            Log::warning('Application deletion initiated', [
                                'application_id' => $record->id,
                                'application_name' => $record->name
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error in Application delete action before hook', [
                                'application_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                        }
                    })
                    ->successNotificationTitle('Application deleted successfully')
                    ->failureNotificationTitle('Failed to delete application')
                    ->failureNotification(function ($exception) {
                        Log::error('Application delete action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return 'An error occurred while deleting the application. Please try again.';
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, $records) {
                            try {
                                Log::warning('Bulk application deletion initiated', [
                                    'count' => $records->count(),
                                    'application_ids' => $records->pluck('id')->toArray()
                                ]);
                            } catch (Exception $e) {
                                Log::error('Error in bulk delete action before hook', [
                                    'error' => $e->getMessage()
                                ]);
                            }
                        })
                        ->successNotificationTitle('Applications deleted successfully')
                        ->failureNotificationTitle('Failed to delete applications')
                        ->failureNotification(function ($exception) {
                            Log::error('Bulk application delete action failed', [
                                'error' => $exception->getMessage(),
                                'trace' => $exception->getTraceAsString()
                            ]);
                            return 'An error occurred while deleting the applications. Please try again.';
                        }),
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
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
