<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'api_key'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($application) {
            try {
                if (empty($application->api_key)) {
                    $maxRetries = 5;
                    $retryCount = 0;

                    while ($retryCount < $maxRetries) {
                        $apiKey = Str::random(32);
                        if (!static::where('api_key', $apiKey)->exists()) {
                            $application->api_key = $apiKey;
                            return;
                        }
                        $retryCount++;
                        Log::warning('API key collision detected in model event', [
                            'model' => static::class,
                            'retry_count' => $retryCount,
                            'application_name' => $application->name ?? 'unnamed'
                        ]);
                    }

                    throw new Exception('Failed to generate unique API key in model event');
                }
            } catch (Exception $e) {
                Log::error('Error in Application creating event', [
                    'error' => $e->getMessage(),
                    'application_name' => $application->name ?? 'unnamed'
                ]);
                throw $e;
            }
        });
    }

    protected static function booted()
    {
        static::created(function ($application) {
            try {
                // Handle fields creation if they exist in the request
                if (request()->has('fields')) {
                    $fields = request()->input('fields');

                    if (!is_array($fields)) {
                        Log::warning('Invalid fields data format in Application created event', [
                            'application_id' => $application->id,
                            'fields_data' => $fields
                        ]);
                        return;
                    }

                    foreach ($fields as $index => $field) {
                        try {
                            if (!isset($field['name'], $field['data_type'])) {
                                Log::warning('Invalid field data structure', [
                                    'application_id' => $application->id,
                                    'field_index' => $index,
                                    'field_data' => $field
                                ]);
                                continue;
                            }

                            $application->fields()->create([
                                'name' => $field['name'],
                                'data_type' => $field['data_type'],
                                'description' => $field['description'] ?? null,
                            ]);

                            Log::info('ApplicationField created successfully', [
                                'application_id' => $application->id,
                                'field_name' => $field['name'],
                                'data_type' => $field['data_type']
                            ]);

                        } catch (Exception $fieldError) {
                            Log::error('Failed to create ApplicationField', [
                                'application_id' => $application->id,
                                'field_index' => $index,
                                'field_name' => $field['name'] ?? 'unknown',
                                'error' => $fieldError->getMessage(),
                                'trace' => $fieldError->getTraceAsString()
                            ]);
                            // Continue with other fields even if one fails
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error in Application created event', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't re-throw here to avoid breaking the application creation
            }
        });
    }

    public function applicationFields()
    {
        return $this->hasMany(ApplicationField::class);
    }

    public function tables()
    {
        return $this->belongsToMany(DatabaseTable::class, 'application_table_subscriptions')
            ->withPivot('consumer_group')
            ->withTimestamps();
    }
}
