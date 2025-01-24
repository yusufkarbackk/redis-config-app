<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseFieldSubscription extends Model
{
    use HasFactory;

    protected $table = 'database_field_subscriptions'; // Specify the table name if it's non-standard
    protected $fillable = [
        'database_config_id',
        'application_field_id',
        'table_id', 
    ];

    // Relationships
    public function databaseConfig()
    {
        return $this->belongsTo(DatabaseConfig::class, 'database_config_id');
    }

    public function applicationField()
    {
        return $this->belongsTo(ApplicationField::class, 'application_field_id');
    }

    public function table()
    {
        return $this->belongsTo(Table::class, 'table_id');
    }
}
