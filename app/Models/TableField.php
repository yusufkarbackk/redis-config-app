<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableField extends Model
{
    use HasFactory;

    protected $fillable = ['table_id', 'name', 'data_type'];

    public function databaseTable()
    {
        return $this->belongsTo(DatabaseTable::class, 'table_id', 'id');
    }

    public function applicationField()
    {
        return $this->belongsTo(ApplicationField::class, 'application_field_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}
