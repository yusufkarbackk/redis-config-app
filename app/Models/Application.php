<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'api_key'];

    protected static function boot() {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->api_key)) {
                $application->api_key = Str::random(32);
            }
        });
    }

    protected static function booted()
    {
        static::created(function ($application) {
            // Handle fields creation if they exist in the request
            if (request()->has('fields')) {
                $fields = request()->input('fields');
                foreach ($fields as $field) {
                    $application->fields()->create([
                        'name' => $field['name'],
                        'data_type' => $field['data_type'],
                        'description' => $field['description'] ?? null,
                    ]);
                }
            }
        });
    }

    public function applicationFields() {
        return $this->hasMany(ApplicationField::class);
    }

    public function tables() {
        return $this->belongsToMany(DatabaseTable::class, 'application_database_table')
        ->withPivot('consumer_group')
        ->withTimestamps();
    }
}
