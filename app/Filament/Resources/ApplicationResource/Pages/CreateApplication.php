<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Exception;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            // Additional validation for required fields
            if (!isset($data['fields']) || empty($data['fields']) || !is_array($data['fields'])) {
                Log::warning('Application creation attempted without required fields', [
                    'name' => $data['name'] ?? 'unknown',
                    'fields_data' => $data['fields'] ?? null
                ]);
                // This will be handled by form validation, but log for monitoring
            }

            // Validate that each field has required data
            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $index => $field) {
                    if (empty($field['name']) || empty($field['data_type'])) {
                        Log::warning('Incomplete field data in application creation', [
                            'application_name' => $data['name'] ?? 'unknown',
                            'field_index' => $index,
                            'field_name' => $field['name'] ?? 'empty',
                            'data_type' => $field['data_type'] ?? 'empty'
                        ]);
                    }
                }
            }

            // Log the creation attempt
            Log::info('Application creation attempt', [
                'name' => $data['name'] ?? 'unknown',
                'description_length' => strlen($data['description'] ?? ''),
                'fields_count' => isset($data['fields']) ? count($data['fields']) : 0
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Error in mutateFormDataBeforeCreate', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return $data;
        }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $record = parent::handleRecordCreation($data);

            Log::info('Application created successfully', [
                'application_id' => $record->id,
                'name' => $record->name,
                'api_key' => substr($record->api_key, 0, 8) . '...' // Log only prefix for security
            ]);

            return $record;
        } catch (Exception $e) {
            Log::error('Failed to create application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_name' => $data['name'] ?? 'unknown'
            ]);

            throw $e;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Application created successfully';
    }

    protected function getFailedNotificationTitle(): ?string
    {
        return 'Failed to create application';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
