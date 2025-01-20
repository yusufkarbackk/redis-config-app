<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'consumer_group ',
        'tables'
    ];

    protected $hidden = ['password'];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate consumer group if not set
        static::creating(function ($config) {
            if (!$config->consumer_group) {
                $config->consumer_group = 'group:' . str()->random(16);
            }
        });
    }

    public function fields()
    {
        return $this->belongsToMany(
            ApplicationField::class,
            'database_field_subscriptions',
            'database_config_id',
            'application_field_id'
        )->withTimestamps();
    }

    public function subscribeFields()
    {
        return $this->belongsToMany(
            ApplicationField::class,
            'database_field_subscription',
            'database_config_id',
            'application_field_id'
        );
    }
}
