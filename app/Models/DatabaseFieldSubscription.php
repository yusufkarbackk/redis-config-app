<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseFieldSubscription extends Model
{
    use HasFactory;

    protected $table = 'database_field_subscriptions'; // Specify the table name if it's non-standard
    protected $fillable = [
        'application_table_subscription_id',
        'application_field_id',
        'mapped_to',
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
        return $this->belongsTo(DatabaseTable::class, 'table_id');
    }

    public function applicationTableSubscription()
    {
        return $this->belongsTo(ApplicationTableSubscription::class);
    }
}
