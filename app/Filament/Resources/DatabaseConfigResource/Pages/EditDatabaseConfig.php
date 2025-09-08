<?php

namespace App\Filament\Resources\DatabaseConfigResource\Pages;

use App\Filament\Resources\DatabaseConfigResource;
use DB;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;

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


        //  dd($formData);
        if (!isset($formData['password'])) {
            if ($this->record->password == "") {
                $formData['password'] = "";
            } else {
                $formData['password'] = decrypt($this->record->password);
            }
        }
        else {
            $formData['password'] = decrypt($formData['password']);
        }

        // Log::info('Checking database connection with form data: ', $formData);
        // if (empty($password)) {
        //     $password = decrypt($this->record->password);
        // }
        //dd($password);
        // Tentukan driver sesuai pilihan user
        //$driver = $formData['connection_type'] === 'pgsql' ? 'pgsql' : 'mysql';


        $config = [
            'driver' => $formData['connection_type'],
            'host' => $formData['host'],
            'port' => $formData['port'],
            'database' => $formData['database_name'],
            'username' => $formData['username'],
            'password' => $formData['password'], // Decrypt password jika diperlukan
        ];

        try {
            // Set konfigurasi koneksi sementara
            config([
                'database.connections.temp_check' => $config,
            ]);

            // Test koneksi
            FacadesDB::connection('temp_check')->getPdo();

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
            FacadesDB::purge('temp_check');
        }
    }
}
