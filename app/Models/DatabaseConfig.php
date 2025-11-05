<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'connection_type',
        'host',
        'port',
        'database_name',
        'username',
        'password',
    ];

    protected $hidden = ['password'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($databaseConfig) {
            try {
                Log::info('DatabaseConfig creation initiated', [
                    'name' => $databaseConfig->name ?? 'unnamed',
                    'connection_type' => $databaseConfig->connection_type ?? 'unknown',
                    'host' => $databaseConfig->host ?? 'unknown'
                ]);
            } catch (Exception $e) {
                Log::error('Error in DatabaseConfig creating event', [
                    'error' => $e->getMessage()
                ]);
            }
        });

        static::created(function ($databaseConfig) {
            try {
                Log::info('DatabaseConfig created successfully', [
                    'id' => $databaseConfig->id,
                    'name' => $databaseConfig->name,
                    'connection_type' => $databaseConfig->connection_type,
                    'host' => $databaseConfig->host
                ]);
            } catch (Exception $e) {
                Log::error('Error in DatabaseConfig created event', [
                    'id' => $databaseConfig->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        });

        static::updating(function ($databaseConfig) {
            try {
                Log::info('DatabaseConfig update initiated', [
                    'id' => $databaseConfig->id,
                    'name' => $databaseConfig->name ?? 'unnamed',
                    'changes' => $databaseConfig->getDirty()
                ]);
            } catch (Exception $e) {
                Log::error('Error in DatabaseConfig updating event', [
                    'id' => $databaseConfig->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        });

        static::updated(function ($databaseConfig) {
            try {
                Log::info('DatabaseConfig updated successfully', [
                    'id' => $databaseConfig->id,
                    'name' => $databaseConfig->name
                ]);
            } catch (Exception $e) {
                Log::error('Error in DatabaseConfig updated event', [
                    'id' => $databaseConfig->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        });

        static::deleting(function ($databaseConfig) {
            try {
                $dependentTables = $databaseConfig->tables()->count();
                if ($dependentTables > 0) {
                    Log::warning('Attempted to delete DatabaseConfig with dependent tables', [
                        'id' => $databaseConfig->id,
                        'name' => $databaseConfig->name,
                        'dependent_tables' => $dependentTables
                    ]);
                    throw new Exception("Cannot delete database configuration with {$dependentTables} dependent table(s)");
                }

                Log::warning('DatabaseConfig deletion initiated', [
                    'id' => $databaseConfig->id,
                    'name' => $databaseConfig->name,
                    'connection_type' => $databaseConfig->connection_type
                ]);
            } catch (Exception $e) {
                Log::error('Error in DatabaseConfig deleting event', [
                    'id' => $databaseConfig->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function tables()
    {
        return $this->hasMany(DatabaseTable::class, 'database_config_id');
    }


    // public function fields()
    // {
    //     return $this->belongsToMany(
    //         ApplicationField::class,
    //         'database_field_subscriptions',
    //         'database_config_id',
    //         'application_field_id'
    //     )->withTimestamps();
    // }

    // public function tables()
    // {
    //     return $this->hasMany(Table::class, 'database_config_id');
    // }

    // public function subscribeFields()
    // {
    //     return $this->belongsToMany(
    //         ApplicationField::class,
    //         'database_field_subscription',
    //         'database_config_id',
    //         'application_field_id'
    //     );
    // }
}
