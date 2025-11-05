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
use Symfony\Contracts\Service\Attribute\Required;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class DatabaseConfigResource extends Resource
{
    protected static ?string $model = DatabaseConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    /**
     * Validate host format (IP address or hostname)
     */
    private static function validateHost(string $host): bool
    {
        // Check if it's a valid IP address (IPv4 or IPv6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if it's a valid hostname
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host);
    }

    /**
     * Test database connection with error handling
     */
    private static function testDatabaseConnection(array $data): array
    {
        try {
            $config = [
                'driver' => $data['connection_type'],
                'host' => $data['host'],
                'port' => $data['port'],
                'database' => $data['database_name'],
                'username' => $data['username'],
                'password' => $data['password'] ?? '',
            ];

            if ($data['connection_type'] === 'pgsql') {
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

            // Create temporary connection
            $connectionName = 'temp_test_' . uniqid();
            config(["database.connections.{$connectionName}" => $config]);

            $connection = \Illuminate\Support\Facades\DB::connection($connectionName);
            $connection->getPdo(); // Test connection

            \Illuminate\Support\Facades\DB::purge($connectionName);

            Log::info('Database connection test successful', [
                'connection_type' => $data['connection_type'],
                'host' => $data['host'],
                'port' => $data['port'],
                'database' => $data['database_name']
            ]);

            return ['success' => true, 'message' => 'Connection successful'];

        } catch (\PDOException $e) {
            Log::error('Database connection test failed (PDO Error)', [
                'connection_type' => $data['connection_type'] ?? 'unknown',
                'host' => $data['host'] ?? 'unknown',
                'port' => $data['port'] ?? 'unknown',
                'database' => $data['database_name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'message' => 'Database connection failed: ' . self::getDatabaseErrorMessage($e)
            ];
        } catch (Exception $e) {
            Log::error('Database connection test failed (General Error)', [
                'connection_type' => $data['connection_type'] ?? 'unknown',
                'host' => $data['host'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user-friendly database error messages
     */
    private static function getDatabaseErrorMessage(\PDOException $e): string
    {
        $message = $e->getMessage();

        // Common database connection errors
        if (str_contains($message, 'Access denied for user')) {
            return 'Invalid username or password';
        }

        if (str_contains($message, 'Unknown database')) {
            return 'Database does not exist';
        }

        if (str_contains($message, 'Connection refused')) {
            return 'Cannot connect to server - check host and port';
        }

        if (str_contains($message, 'timeout')) {
            return 'Connection timeout - server may be unreachable';
        }

        if (str_contains($message, 'SSL') || str_contains($message, 'encryption')) {
            return 'SSL/TLS connection error';
        }

        return 'Database connection error';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Database Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Database configuration name is required.',
                                'max' => 'Name must not exceed 255 characters.',
                                'unique' => 'A database configuration with this name already exists.'
                            ])
                            ->helperText('Enter a descriptive name for this database configuration.'),

                        Forms\Components\Select::make('connection_type')
                            ->options([
                                'mysql' => 'MySQL',
                                'pgsql' => 'PostgreSQL'
                            ])
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Database type is required.'
                            ])
                            ->helperText('Select the database server type.'),

                        Forms\Components\TextInput::make('host')
                            ->required()
                            ->default('localhost')
                            ->validationMessages([
                                'required' => 'Host is required.',
                                'custom' => 'Please enter a valid IP address or hostname.'
                            ])
                            ->helperText('Enter the server IP address or hostname.')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, callable $fail) {
                                        if (!self::validateHost($value)) {
                                            $fail('Please enter a valid IP address or hostname.');
                                        }
                                    };
                                }
                            ]),

                        Forms\Components\TextInput::make('port')
                            ->numeric()
                            ->required()
                            ->default(function ($get) {
                                return $get('connection_type') === 'mysql' ? 3306 : 5432;
                            })
                            ->minValue(1)
                            ->maxValue(65535)
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Port is required.',
                                'numeric' => 'Port must be a number.',
                                'min' => 'Port must be between 1 and 65535.',
                                'max' => 'Port must be between 1 and 65535.'
                            ])
                            ->helperText('Enter the database server port (1-65535).'),

                        Forms\Components\TextInput::make('database_name')
                            ->required()
                            ->maxLength(64)
                            ->validationMessages([
                                'required' => 'Database name is required.',
                                'max' => 'Database name must not exceed 64 characters.',
                                'regex' => 'Database name can only contain letters, numbers, and underscores.'
                            ])
                            ->rules([
                                'regex:/^[a-zA-Z0-9_]+$/'
                            ])
                            ->helperText('Enter the name of the database.'),

                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Username is required.',
                                'max' => 'Username must not exceed 255 characters.'
                            ])
                            ->helperText('Enter the database username.'),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrateStateUsing(function ($state) {
                                try {
                                    return $state ? encrypt($state) : null;
                                } catch (Exception $e) {
                                    Log::error('Password encryption failed', [
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString()
                                    ]);
                                    throw new Exception('Failed to encrypt password');
                                }
                            })
                            ->default('')
                            ->dehydrated(fn($state) => filled($state))
                            ->helperText('Leave empty to keep existing password.')
                            ->autocomplete('new-password')
                            ->required(fn ($context) => $context === 'create')
                            ->validationMessages([
                                'required' => 'Password is required for new database configurations.'
                            ]),

                        Forms\Components\Placeholder::make('connection_test')
                            ->label('Connection Test')
                            ->content(function ($get, $set) {
                                $required = [
                                    'connection_type' => $get('connection_type'),
                                    'host' => $get('host'),
                                    'port' => $get('port'),
                                    'database_name' => $get('database_name'),
                                    'username' => $get('username'),
                                    'password' => $get('password') // Will be decrypted in model
                                ];

                                $missing = array_filter($required, fn($value) => empty($value));

                                if (!empty($missing)) {
                                    return '⚠️ Fill in all connection details to test.';
                                }

                                $testResult = self::testDatabaseConnection($required);

                                if ($testResult['success']) {
                                    return '✅ ' . $testResult['message'];
                                } else {
                                    return '❌ ' . $testResult['message'];
                                }
                            })
                            ->columnSpanFull(),
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
                Tables\Actions\EditAction::make()
                    ->before(function (Tables\Actions\EditAction $action, $record) {
                        try {
                            Log::info('DatabaseConfig edit action initiated', [
                                'config_id' => $record->id,
                                'config_name' => $record->name
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error in DatabaseConfig edit action before hook', [
                                'config_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                        }
                    })
                    ->successNotificationTitle('Database configuration updated successfully')
                    ->failureNotificationTitle('Failed to update database configuration')
                    ->failureNotification(function ($exception) {
                        Log::error('DatabaseConfig edit action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return 'An error occurred while updating the database configuration. Please try again.';
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, $record) {
                        try {
                            Log::warning('DatabaseConfig deletion initiated', [
                                'config_id' => $record->id,
                                'config_name' => $record->name,
                                'connection_type' => $record->connection_type
                            ]);

                            // Check for dependent records
                            $dependentTables = $record->tables()->count();
                            if ($dependentTables > 0) {
                                Log::warning('Attempted to delete DatabaseConfig with dependent tables', [
                                    'config_id' => $record->id,
                                    'dependent_tables' => $dependentTables
                                ]);
                                throw new Exception("Cannot delete this database configuration. {$dependentTables} table(s) are linked to it.");
                            }

                        } catch (Exception $e) {
                            Log::error('Error in DatabaseConfig delete action before hook', [
                                'config_id' => $record->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                            throw $e;
                        }
                    })
                    ->successNotificationTitle('Database configuration deleted successfully')
                    ->failureNotificationTitle('Failed to delete database configuration')
                    ->failureNotification(function ($exception) {
                        Log::error('DatabaseConfig delete action failed', [
                            'error' => $exception->getMessage(),
                            'trace' => $exception->getTraceAsString()
                        ]);
                        return $exception->getMessage() ?: 'An error occurred while deleting the database configuration. Please try again.';
                    }),
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
