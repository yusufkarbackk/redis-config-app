<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessStreamMessage; // Pastikan namespace Job Anda sudah benar
use Throwable;

class ListenToStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stream:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to a Redis Stream and dispatch jobs for processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // --- 1. Konfigurasi ---
        // Mengambil konfigurasi dari file .env atau config, membuatnya lebih fleksibel.
        // Sama seperti yang Anda definisikan sebelumnya.
        $streamKey = 'app:data:stream';
        $groupName = 'integrator_group'; // Nama grup Anda
        $consumerName = 'processor_' . gethostname(); // Membuat nama consumer unik per host

        // $this->info("Starting Redis Stream listener...");
        // $this->line("Stream Key : <fg=cyan>$streamKey</>");
        // $this->line("Group Name : <fg=cyan>$groupName</>");
        // $this->line("Consumer Name: <fg=cyan>$consumerName</>");

        // --- 2. Membuat Consumer Group ---
        // Logika ini sama persis seperti di constructor Anda,
        // yaitu memastikan group sudah ada.
        try {
            Redis::xgroup('CREATE', $streamKey, $groupName, '0', 'MKSTREAM');
            dump("Consumer group '$groupName' created or already exists.");
        } catch (Throwable $e) {
            // Abaikan error jika group sudah ada (BUSYGROUP)
            if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
                dump("Could not create consumer group: " . $e->getMessage());
                return 1; // Keluar dengan status error
            }
        }

        dump("Waiting for new messages...");

        // --- 3. Looping untuk Mendengarkan Data ---
        // Ini adalah pengganti dari metode pop() Anda.
        while (true) {
            try {
                // Membaca batch data dari stream.
                // Logikanya sama dengan xReadGroup yang Anda gunakan.
                $messages = Redis::xreadgroup(
                    $groupName,
                    $consumerName,
                    [$streamKey => '>'],
                    10,   // Ambil 10 pesan sekaligus (sama seperti Anda)
                    2000,  // Tunggu 2 detik jika kosong, lalu loop lagi
                );

                if (empty($messages[$streamKey])) {
                    continue; // Tidak ada pesan, lanjut ke iterasi berikutnya
                }

                $processedIds = [];
                $this->info($processedIds);
                foreach ($messages[$streamKey] as $id => $fields) {
                    // Kirim Job ke antrian Redis default.
                    // Ini adalah inti dari pendelegasian tugas.
                    $this->info('stream-messages', $fields);

                    ProcessStreamMessage::dispatch($id, $fields)
                        ->onConnection('redis') // Pastikan worker Anda berjalan di koneksi redis
                        ->onQueue('redis');   // Anda bisa ganti nama queue jika perlu

                    // Kumpulkan ID pesan yang akan di-ACK
                    $processedIds[] = $id;
                }

                // Lakukan ACK untuk semua pesan yang sudah berhasil di-dispatch,
                // ini lebih efisien daripada ACK satu per satu.
                if (!empty($processedIds)) {
                    Redis::xack($streamKey, $groupName, $processedIds);
                    $this->info('oke');
                    $this->info(count($processedIds) . ' jobs dispatched to the queue.');
                }
            } catch (Throwable $e) {
                // Jika terjadi error (misal koneksi Redis putus), log error tersebut
                // dan tunggu sejenak sebelum mencoba lagi agar tidak crash.
                Log::error('stream-listener-error: ' . $e->getMessage());
                $this->error('An error occurred: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}
