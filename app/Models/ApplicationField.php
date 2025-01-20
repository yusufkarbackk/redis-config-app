<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationField extends Model
{
    use HasFactory;


    protected $fillable = ['name', 'data_type', 'description', 'application_id'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function databaseSubscriptions()
    {
        return $this->belongsToMany(
            DatabaseConfig::class,
            'database_field_subscriptions',
            'application_field_id',
            'database_config_id'
        );
    }
}
