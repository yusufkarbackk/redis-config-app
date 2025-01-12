<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'connection_type', 'host', 'port', 'database_name', 'username', 'password', 'consumer_group '
    ];

    protected $hidden = ['password'];

    public function subscribeFields() {
        return $this->belongsToMany(ApplicationField::class, 'database_field_subscription', 'database_config_id',
            'application_field_id');
    }
}
