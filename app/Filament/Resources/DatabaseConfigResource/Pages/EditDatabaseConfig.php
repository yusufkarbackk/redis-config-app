<?php

namespace App\Filament\Resources\DatabaseConfigResource\Pages;

use App\Filament\Resources\DatabaseConfigResource;
use DB;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Log;

class EditDatabaseConfig extends EditRecord
{
    protected static string $resource = DatabaseConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Update Configuration')
                ->color('primary')
                ->submit('update'),
            Action::make('check_connection')
                ->label('Check Database Connection')
                ->color('secondary')
                ->action('checkDatabaseConnection'),
        ];
    }

    public function checkDatabaseConnection()
    {
        // Ambil data form
        $formData = $this->form->getState();
        $password = $formData['password'] ?? null;

        Log::info('Checking database connection with form data: ', $formData);
        if (empty($password)) {
            $password = decrypt($this->record->password);
        }
        //dd($password);
        // Tentukan driver sesuai pilihan user
        $driver = $formData['connection_type'] === 'pgsql' ? 'pgsql' : 'mysql';


        $config = [
            'driver' => $driver,
            'host' => $formData['host'],
            'port' => $formData['port'],
            'database' => $formData['database_name'],
            'username' => $formData['username'],
            'password' => $password,
        ];

        try {
            // Set konfigurasi koneksi sementara
            config([
                'database.connections.temp_check' => $config,
            ]);

            // Test koneksi
            DB::connection('temp_check')->getPdo();

            Notification::make()
                ->title('Koneksi Berhasil')
                ->success()
                ->body('Berhasil terhubung ke database!')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Koneksi Gagal')
                ->danger()
                ->body('Gagal terkoneksi ke database: ' . $e->getMessage())
                ->send();
        } finally {
            // Bersihkan koneksi agar tidak mengganggu koneksi lain
            DB::purge('temp_check');
        }
    }
}
