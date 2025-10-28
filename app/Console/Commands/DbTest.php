<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DbTest extends Command {
    protected $signature = 'db:test';
    protected $description = 'Test database connection from CLI';

    public function handle() {
        try {
            DB::connection()->getPdo();
            $this->info("Database connection successful!");
        } catch (\Exception $e) {
            $this->error("Could not connect to the database. Please check your configuration.");
            $this->error("Error: " . $e->getMessage());
        }
    }
}