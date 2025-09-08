<?php

namespace App\Filament\Resources\DatabaseConfigResource\Pages;

use App\Filament\Resources\DatabaseConfigResource;
use DB;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;

class CreateDatabaseConfig extends CreateRecord
{
    protected static string $resource = DatabaseConfigResource::class;

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->color('primary')
                ->submit('create'),
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
        // if (!isset($formData['password'])) {
        //     $formData['password'] = ""; 
        // }
        // Tentukan driver sesuai pilihan user
        // $driver = $formData['connection_type'] === 'pgsql' ? 'pgsql' : 'mysql';
        //Log::info('connection type', [$formData['connection_type']]);
        //dd($formData);

        $password = !isset($formData['password']) || $formData['password'] == null ? '' : decrypt($formData['password']);
        //$password = $formData['password'] == "" ? '' : decrypt($formData['password']);

        $config = [
            'driver' => $formData['connection_type'],
            'host' => $formData['host'],
            'port' => $formData['port'],
            'database' => $formData['database_name'],
            'username' => $formData['username'],
            'password' => $password, // Decrypt password jika diperlukan
        ];
        //dd($config);
        Log::info('Checking database connection with config: ', $config);
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

    // public function getRedirectUrl(): string
    // {
    //     return 'admin/database-configs'; 
    // }
}
