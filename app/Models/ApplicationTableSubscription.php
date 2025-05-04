<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationTableSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'database_table_id',
        'consumer_group',
    ];
    
    public function databaseTable()
    {
        return $this->belongsTo(DatabaseTable::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function fieldMappings()
    {
        return $this->hasMany(DatabaseFieldSubscription::class);
    }

}
