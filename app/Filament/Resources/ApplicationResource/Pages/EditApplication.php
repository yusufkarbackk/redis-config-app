<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Exception;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            Log::info('Application edit page loaded', [
                'application_id' => $this->record->id,
                'name' => $this->record->name
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Error loading application edit page', [
                'application_id' => $this->record->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $data;
        }
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            Log::info('Application update attempt', [
                'application_id' => $record->id,
                'name' => $data['name'] ?? 'unknown',
                'fields_count' => isset($data['fields']) ? count($data['fields']) : 0
            ]);

            $updatedRecord = parent::handleRecordUpdate($record, $data);

            Log::info('Application updated successfully', [
                'application_id' => $updatedRecord->id,
                'name' => $updatedRecord->name
            ]);

            return $updatedRecord;
        } catch (Exception $e) {
            Log::error('Failed to update application', [
                'application_id' => $record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_name' => $data['name'] ?? 'unknown'
            ]);

            throw $e;
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Application updated successfully';
    }

    protected function getFailedNotificationTitle(): ?string
    {
        return 'Failed to update application';
    }
}
