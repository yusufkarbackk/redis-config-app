<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseTable extends Model
{
    use HasFactory;

    protected $fillable = ['database_config_id', 'table_name', 'application_id', 'consumer group'];

    public function database()
    {
        return $this->belongsTo(DatabaseConfig::class, 'database_config_id');
    }

    public function application()
    {
        return $this->belongsToMany(Application::class, 'application_database_table')
        ->withPivot('consumer_group')
        ->withTimestamps();
    }

    public function tableFields()
    {
        return $this->hasMany(TableField::class, 'table_id', 'id');
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
}
