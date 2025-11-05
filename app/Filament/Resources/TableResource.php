<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TableResource\Pages;
use App\Models\Application;
use App\Models\ApplicationField;
use App\Models\DatabaseConfig;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Exception;

class TableResource extends Resource
{
    protected static ?string $model = DatabaseTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * Safely decrypt database password
     */
    private static function decryptPassword(?string $encryptedPassword): string
    {
        try {
            return $encryptedPassword ? decrypt($encryptedPassword) : '';
        } catch (Exception $e) {
            Log::error('Failed to decrypt database password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Invalid password format. Please re-enter the database password.');
        }
    }

    /**
     * Build dynamic database configuration
     */
    private static function buildDatabaseConfig(DatabaseConfig $dbConfig): array
    {
        try {
            $password = self::decryptPassword($dbConfig->password);

            $config = [
                'driver' => $dbConfig->connection_type,
                'host' => $dbConfig->host,
                'port' => $dbConfig->port,
                'database' => $dbConfig->database_name,
                'username' => $dbConfig->username,
                'password' => $password,
            ];

            if ($dbConfig->connection_type === 'pgsql') {
                $config['charset'] = 'utf8';
                $config['prefix'] = '';
                $config['prefix_indexes'] = true;
                $config['search_path'] = 'public';
            } else {
                $config['charset'] = 'utf8mb4';
                $config['collation'] = 'utf8mb4_unicode_ci';
                $config['prefix'] = '';
                $config['prefix_indexes'] = true;
            }

            return $config;
        } catch (Exception $e) {
            Log::error('Failed to build database configuration', [
                'db_config_id' => $dbConfig->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Safely list tables from database
     */
    private static function listDatabaseTables(DatabaseConfig $dbConfig): array
    {
        try {
            $config = self::buildDatabaseConfig($dbConfig);

            $connectionName = 'temp_table_check_' . uniqid();
            config(["database.connections.{$connectionName}" => $config]);

            $connection = DB::connection($connectionName);
            $schema = $connection->getDoctrineSchemaManager();
            $tables = $schema->listTableNames();

            DB::purge($connectionName);

            Log::info('Successfully listed database tables', [
                'db_config_id' => $dbConfig->id,
                'table_count' => count($tables),
                'connection_type' => $dbConfig->connection_type
            ]);

            return collect($tables)->mapWithKeys(fn($table) => [$table => $table])->toArray();

        } catch (\PDOException $e) {
            DB::purge($connectionName ?? 'temp_table_check');
            Log::error('Failed to list database tables (PDO Error)', [
                'db_config_id' => $dbConfig->id,
                'connection_type' => $dbConfig->connection_type,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw new Exception('Failed to connect to database: ' . self::getDatabaseErrorMessage($e));
        } catch (Exception $e) {
            DB::purge($connectionName ?? 'temp_table_check');
            Log::error('Failed to list database tables (General Error)', [
                'db_config_id' => $dbConfig->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to retrieve table list: ' . $e->getMessage());
        }
    }

    /**
     * Safely list table columns
     */
    private static function listTableColumns(DatabaseConfig $dbConfig, string $tableName): array
    {
        try {
            $config = self::buildDatabaseConfig($dbConfig);
            $connectionKey = 'temp_table_check_' . md5($dbConfig->id . $tableName);

            config(["database.connections.{$connectionKey}" => $config]);
            $columns = Schema::connection($connectionKey)->getColumnListing($tableName);

            DB::purge($connectionKey);

            Log::info('Successfully listed table columns', [
                'db_config_id' => $dbConfig->id,
                'table_name' => $tableName,
                'column_count' => count($columns)
            ]);

            return collect($columns)->mapWithKeys(fn($column) => [$column => $column])->toArray();

        } catch (\PDOException $e) {
            DB::purge($connectionKey ?? 'temp_table_check');
            Log::error('Failed to list table columns (PDO Error)', [
                'db_config_id' => $dbConfig->id,
                'table_name' => $tableName,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to access table columns: ' . self::getDatabaseErrorMessage($e));
        } catch (Exception $e) {
            DB::purge($connectionKey ?? 'temp_table_check');
            Log::error('Failed to list table columns (General Error)', [
                'db_config_id' => $dbConfig->id,
                'table_name' => $tableName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to retrieve column list: ' . $e->getMessage());
        }
    }

    /**
     * Get user-friendly database error messages
     */
    private static function getDatabaseErrorMessage(\PDOException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Access denied for user')) {
            return 'Invalid database credentials';
        }

        if (str_contains($message, 'Unknown database')) {
            return 'Database does not exist';
        }

        if (str_contains($message, 'Connection refused')) {
            return 'Cannot connect to database server';
        }

        if (str_contains($message, "doesn't exist")) {
            return 'Table does not exist in database';
        }

        if (str_contains($message, 'timeout')) {
            return 'Connection timeout - server may be unreachable';
        }

        return 'Database access error';
    }

    /**
     * Validate consumer group format
     */
    private static function validateConsumerGroup(string $consumerGroup): bool
    {
        // Consumer group should follow Redis stream naming conventions
        return (bool) preg_match('/^[a-zA-Z0-9_\-:.]+$/', $consumerGroup);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Table Configurations')->schema([
                Select::make('database_config_id')
                    ->label('Database')
                    ->relationship('database', 'name')
                    ->required()
                    ->reactive(),

                Select::make('table_name')
                    ->label('Table Name')
                    ->required()
                    ->reactive()
                    ->validationMessages([
                        'required' => 'Table selection is required.'
                    ])
                    ->helperText('Select a table from the database.')
                    ->options(function (callable $get) {
                        try {
                            $databaseId = $get('database_config_id');
                            if (!$databaseId) {
                                return [];
                            }

                            $dbConfig = DatabaseConfig::find($databaseId);
                            if (!$dbConfig) {
                                Log::warning('Database configuration not found', ['database_id' => $databaseId]);
                                return [];
                            }

                            return self::listDatabaseTables($dbConfig);

                        } catch (Exception $e) {
                            Log::error('Failed to load table options', [
                                'database_id' => $get('database_config_id'),
                                'error' => $e->getMessage()
                            ]);
                            // Return empty array to prevent form breaking
                            return [];
                        }
                    }),

                Repeater::make('applicationSubscriptions')
                    ->label('Subscribed Applications')
                    ->relationship()
                    ->schema([
                        Select::make('application_id')
                            ->label('Application')
                            ->options(Application::all()->pluck('name', 'id')->toArray())
                            ->reactive()
                            ->required(),

                        TextInput::make('consumer_group')
                            ->label('Consumer Group')
                            ->default(function () {
                                try {
                                    $consumerGroup = 'group:' . Str::random(16);
                                    if (!self::validateConsumerGroup($consumerGroup)) {
                                        Log::warning('Generated consumer group failed validation', [
                                            'consumer_group' => $consumerGroup
                                        ]);
                                        return 'group:default_' . time();
                                    }
                                    return $consumerGroup;
                                } catch (Exception $e) {
                                    Log::error('Failed to generate consumer group', [
                                        'error' => $e->getMessage()
                                    ]);
                                    return 'group:default_' . time();
                                }
                            })
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->helperText('Automatically generated unique identifier for the consumer group.'),

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

                                Select::make('mapped_to')
                                    ->label('Map To (Table Field)')
                                    ->required()
                                    ->reactive()
                                    ->validationMessages([
                                        'required' => 'Table field mapping is required.'
                                    ])
                                    ->helperText('Select the corresponding table column for this application field.')
                                    ->options(function (callable $get) {
                                        try {
                                            $databaseConfigId = $get('../../../../database_config_id');
                                            $tableName = $get('../../../../table_name');

                                            if (!$databaseConfigId || !$tableName) {
                                                return [];
                                            }

                                            $dbConfig = DatabaseConfig::find($databaseConfigId);
                                            if (!$dbConfig) {
                                                Log::warning('Database configuration not found for field mapping', [
                                                    'database_config_id' => $databaseConfigId,
                                                    'table_name' => $tableName
                                                ]);
                                                return [];
                                            }

                                            // Use caching with error handling
                                            $cacheKey = "table_fields_{$databaseConfigId}_{$tableName}";
                                            return cache()->remember($cacheKey, 300, function () use ($dbConfig, $tableName) {
                                                return self::listTableColumns($dbConfig, $tableName);
                                            });

                                        } catch (Exception $e) {
                                            Log::error('Failed to load table column options for field mapping', [
                                                'database_config_id' => $get('../../../../database_config_id'),
                                                'table_name' => $get('../../../../table_name'),
                                                'error' => $e->getMessage()
                                            ]);
                                            // Return empty array to prevent form breaking
                                            return [];
                                        }
                                    }),
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
                TextColumn::make(name: 'database.name'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->before(function (Tables\Actions\EditAction $action, $record) {
                        try {
                            Log::info('Table edit action initiated', [
                                'table_id' => $record->id,
                                'table_name' => $record->table_name,
                                'database_id' => $record->database_config_id
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error in Table edit action before hook', [
                                'table_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                        }
                    })
                    ->successNotificationTitle('Table configuration updated successfully')
                    ->failureNotificationTitle('Failed to update table configuration')
                    ->failureNotification(function ($exception) {
                        Log::error('Table edit action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return 'An error occurred while updating the table configuration. Please try again.';
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, $record) {
                        try {
                            Log::warning('Table deletion initiated', [
                                'table_id' => $record->id,
                                'table_name' => $record->table_name,
                                'database_id' => $record->database_config_id
                            ]);

                            // Check for dependent application subscriptions
                            $subscriptionCount = $record->applicationSubscriptions()->count();
                            if ($subscriptionCount > 0) {
                                Log::warning('Attempted to delete table with active subscriptions', [
                                    'table_id' => $record->id,
                                    'subscription_count' => $subscriptionCount
                                ]);
                                throw new Exception("Cannot delete this table configuration. {$subscriptionCount} application subscription(s) are linked to it.");
                            }

                        } catch (Exception $e) {
                            Log::error('Error in Table delete action before hook', [
                                'table_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                            throw $e;
                        }
                    })
                    ->successNotificationTitle('Table configuration deleted successfully')
                    ->failureNotificationTitle('Failed to delete table configuration')
                    ->failureNotification(function ($exception) {
                        Log::error('Table delete action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return $exception->getMessage() ?: 'An error occurred while deleting the table configuration. Please try again.';
                    }),
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
