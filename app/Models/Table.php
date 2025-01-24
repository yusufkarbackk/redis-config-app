<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    protected $fillable = ['database_config_id', 'table_name', 'application_id'];

    public function database()
    {
        return $this->belongsTo(DatabaseConfig::class, 'database_id');
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
}
