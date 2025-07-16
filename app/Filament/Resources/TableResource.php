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
use Log;
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
                    ->required()
                    ->reactive(),

                Select::make('table_name')
                    ->label('Table Name')
                    ->required()
                    ->options(function (callable $get) {
                        $databaseId = $get('database_config_id');
                        if (!$databaseId) {
                            return [];
                        }
                        //Log::info('db id ', context: [$databaseId]);
            
                        // Ambil config koneksi dari database (misal model DatabaseConfig)
                        $dbConfig = DatabaseConfig::find($databaseId);
                        if (!$dbConfig) {
                            return [];
                        }

                        // Dekripsi password jika dienkripsi
                        $password = $dbConfig->password ? decrypt($dbConfig->password) : '';

                        // Build config koneksi dinamis
                        $config = [
                            'driver' => $dbConfig->connection_type === 'pgsql' ? 'pgsql' : 'mysql',
                            'host' => $dbConfig->host,
                            'port' => $dbConfig->port,
                            'database' => $dbConfig->database_name,
                            'username' => $dbConfig->username,
                            'password' => $password,
                        ];
                        Log::info('connection type', $config);

                        try {
                            config(['database.connections.temp_table_check' => $config]);
                            $schema = \DB::connection('temp_table_check')->getDoctrineSchemaManager();
                            $tables = $schema->listTableNames();
                            \DB::purge('temp_table_check');
                            // Buat array: ['table1' => 'table1', ...]
                            return collect($tables)->mapWithKeys(fn($t) => [$t => $t])->toArray();
                        } catch (\Throwable $e) {
                            \DB::purge('temp_table_check');
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

                                Select::make('mapped_to')
                                    ->label('Map To (Table Field)')
                                    ->required()
                                    ->options(function (callable $get) {
                                        // 1. Fetch parameter parent
                                        $databaseConfigId = $get('../../../../database_config_id');
                                        $tableName = $get('../../../../table_name');

                                        if (!$databaseConfigId || !$tableName) {
                                            return [];
                                        }

                                        // 2. Fetch config (bisa di-cache kalau ingin)
                                        $dbConfig = DatabaseConfig::find($databaseConfigId);
                                        if (!$dbConfig) {
                                            return [];
                                        }

                                        // Dekripsi password jika dienkripsi
                                        $password = $dbConfig->password ? decrypt($dbConfig->password) : '';
                                        // 3. Build config koneksi dinamis
                                        $connectionKey = 'temp_table_check_' . md5($databaseConfigId . $tableName); // Unique per koneksi-table
                                        $config = [
                                            'driver' => $dbConfig->connection_type === 'pgsql' ? 'pgsql' : 'mysql',
                                            'host' => $dbConfig->host,
                                            'port' => $dbConfig->port,
                                            'database' => $dbConfig->database_name,
                                            'username' => $dbConfig->username,
                                            'password' => $password,
                                        ];

                                        // 4. Caching (Opsional, pakai Laravel cache)
                                        $cacheKey = "table_fields_{$databaseConfigId}_{$tableName}";
                                        return cache()->remember($cacheKey, 60, function () use ($config, $tableName, $connectionKey) {
                                            try {
                                                config(['database.connections.' . $connectionKey => $config]);
                                                $columns = \Schema::connection($connectionKey)->getColumnListing($tableName);
                                                \DB::purge($connectionKey);
                                                return collect($columns)->mapWithKeys(fn($c) => [$c => $c])->toArray();
                                            } catch (\Throwable $e) {
                                                \DB::purge($connectionKey);
                                                return [];
                                            }
                                        });
                                    })
                                    ->reactive()
                                ,
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
