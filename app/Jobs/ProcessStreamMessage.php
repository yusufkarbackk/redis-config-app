<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationTableSubscription;
use App\Models\Log;
use App\Models\ProjectHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Redis;
use PDO;
use Str;

class ProcessStreamMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $messageId;
    public array $payload;
    public Carbon $jobStartedAt;
    public Carbon $processingStartedAt;
    public ?string $streamProcessingStartedAt;

    /**
     * Create a new job instance.
     */
    public function __construct(string $messageId, array $payload)
    {
        $this->messageId = $messageId;
        $this->payload = $payload;
        $this->jobStartedAt = now();
        $this->processingStartedAt = now();
        $this->streamProcessingStartedAt = $payload['processing_started_at'] ?? null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobProcessingStartedAt = now();
        $helper = new ProjectHelper();

        try {
            FacadesLog::info('Processing stream message (Dispatcher)', [
                'message_id' => $this->messageId,
                'stream_processing_started_at' => $this->streamProcessingStartedAt,
                'job_started_at' => $this->jobStartedAt->toIso8601String(),
                'processing_started_at' => $this->processingStartedAt->toIso8601String(),
                'job_processing_started_at' => $jobProcessingStartedAt->toIso8601String()
            ]);

            $dataId = Str::random(8);
            $app = Application::where('api_key', $this->payload['api_key'])->first();

            if (!$app) {
                FacadesLog::warning('No such app found, dropping message', [
                    'api_key' => $this->payload['api_key'] ?? 'not_provided',
                    'message_id' => $this->messageId
                ]);
                return;
            }

            FacadesLog::info('Application found for dispatch', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'message_id' => $this->messageId
            ]);

            // Use multiple timestamps for better timing analysis
            $enqueuedAt = Carbon::parse($this->payload['enqueued_at'] ?? now());
            $sentAt = $this->streamProcessingStartedAt
                ? Carbon::parse($this->streamProcessingStartedAt)
                : $enqueuedAt; // Fallback to enqueued_at if processing_started_at not available

            // Calculate timing metrics
            $queueDelay = $sentAt->diffInSeconds($enqueuedAt);
            $processingDelay = $jobProcessingStartedAt->diffInSeconds($sentAt);

            FacadesLog::info('Timing metrics calculated', [
                'message_id' => $this->messageId,
                'enqueued_at' => $enqueuedAt->toIso8601String(),
                'sent_at' => $sentAt->toIso8601String(),
                'job_started_at' => $this->jobStartedAt->toIso8601String(),
                'job_processing_started_at' => $jobProcessingStartedAt->toIso8601String(),
                'queue_delay_seconds' => $queueDelay,
                'processing_delay_seconds' => $processingDelay
            ]);

            // Remove meta-fields so data_sent is just business data
            $rawData = collect($this->payload)
                ->except(['api_key', 'enqueued_at'])
                ->toArray();

            // Load all table subscriptions for this app
            $subscriptions = ApplicationTableSubscription::with([
                'databaseTable.database',
                'fieldMappings.applicationField',
            ])->where('application_id', $app->id)->get();

            if ($subscriptions->isEmpty()) {
                FacadesLog::warning('No subscriptions found for application', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'message_id' => $this->messageId
                ]);
                return;
            }

            $dispatchedJobs = [];
            $totalMappedSubscriptions = 0;

            // Dispatch separate jobs for each subscription (parallel processing)
            foreach ($subscriptions as $sub) {
                // Build the mapped payload for this table
                $mapped = [];
                foreach ($sub->fieldMappings as $mapping) {
                    $appFieldName = $mapping->applicationField->name;
                    if (isset($this->payload[$appFieldName])) {
                        $mapped[$mapping->mapped_to] = $this->payload[$appFieldName];
                    }
                }

                if (empty($mapped)) {
                    // nothing to insert for this table
                    FacadesLog::info('Skipping subscription - no mapped data', [
                        'subscription_id' => $sub->id,
                        'table_name' => $sub->databaseTable->table_name,
                        'message_id' => $this->messageId
                    ]);
                    continue;
                }

                $totalMappedSubscriptions++;

                try {
                    // Dispatch job to queue for parallel processing
                    $processSubscriptionJob = new ProcessSubscriptionJob(
                        $sub,
                        $this->payload,
                        $rawData,
                        $mapped,
                        $sentAt
                    );

                    dispatch($processSubscriptionJob);
                    $dispatchedJobs[] = [
                        'subscription_id' => $sub->id,
                        'table_name' => $sub->databaseTable->table_name,
                        'database_host' => $sub->databaseTable->database->host,
                    ];

                    FacadesLog::info('Dispatched subscription job for parallel processing', [
                        'message_id' => $this->messageId,
                        'subscription_id' => $sub->id,
                        'table_name' => $sub->databaseTable->table_name,
                        'database_host' => $sub->databaseTable->database->host,
                    ]);

                } catch (\Throwable $e) {
                    FacadesLog::error('Failed to dispatch subscription job', [
                        'message_id' => $this->messageId,
                        'subscription_id' => $sub->id,
                        'table_name' => $sub->databaseTable->table_name,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            FacadesLog::info('Stream message dispatch completed', [
                'message_id' => $this->messageId,
                'app_id' => $app->id,
                'app_name' => $app->name,
                'total_subscriptions' => $subscriptions->count(),
                'mapped_subscriptions' => $totalMappedSubscriptions,
                'dispatched_jobs' => count($dispatchedJobs),
                'dispatch_details' => $dispatchedJobs,
            ]);

        } catch (\Throwable $e) {
            FacadesLog::error('Stream message dispatcher failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message_id' => $this->messageId,
                'exception' => $e
            ]);
        }
    }
}