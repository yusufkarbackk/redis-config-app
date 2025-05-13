<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use function PHPSTORM_META\type;

class DataController extends Controller
{
    public function store(Request $request)
    {

        //validate API key

        $apiKey = $request->header('X-API-Key');

        $application = Application::where('api_key', $apiKey)->select(['name', 'api_key', 'id'])->firstOrFail();
        $validFields = $application->applicationFields()->pluck('name')->toArray();

        $payload = $request->only($validFields);

        $payload['api_key'] = $application->api_key;
        $payload['enqueued_at'] = Carbon::now()->toIso8601String();

        try {
            $MessageId = Redis::command(
                'xadd',
                [
                    env('REDIS_UNIFIED_STREAM'),
                    '*',
                    $payload,
                ]
            );

            //dd($MessageId);
            return response()->json([
                'message' => 'Data received and queued',
                'message_id' => $MessageId,
                "data" => $payload

            ]);
        } catch (\Throwable $th) {
        
            return response()->json([
                'message' => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile()
            ]);
        }
    }
}
