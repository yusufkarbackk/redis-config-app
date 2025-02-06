<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableField extends Model
{
    use HasFactory;

    protected $fillable = ['table_id', 'field_name', 'field_type', 'application_field_id', 'application_id'];

    public function table()
    {
        return $this->belongsTo(DatabaseTable::class, 'table_id');
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
