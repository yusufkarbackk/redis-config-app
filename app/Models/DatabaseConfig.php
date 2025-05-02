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
    ];

    protected $hidden = ['password'];

    protected static function boot()
    {
        parent::boot();
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
