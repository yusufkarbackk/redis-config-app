<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Predis\Client;
use function PHPSTORM_META\type;

class DataController extends Controller
{
    public function store(Request $request)
    {

        //validate API key

        $apiKey = $request->header('X-API-Key');

        $application = Application::where('api_key', $apiKey)->firstOrFail(['id', 'api_key']);
        $validFields = $application->applicationFields()->pluck('name')->toArray();

        $streamKey = env('REDIS_UNIFIED_STREAM', 'app:data:stream');

        $filteredData = $request->only($validFields);
        $filteredData['application_id'] = $application->id;
        $filteredData['api_key'] = $apiKey;
        $filteredData['enqueued_at'] = Carbon::now()->toIso8601String();
        //dd($streamKey);
        try {
            
            $redisClient = new Client();
            $MessageId = $redisClient->xadd(
                $streamKey,     // e.g. "app:data:stream"
                $filteredData ,
                '*'  
            );

            //dd($MessageId);
            return response()->json([
                'message' => 'Data received and queued',
                'message_id' => $MessageId,
                "data" => $filteredData

            ]);
        } catch (\Throwable $th) {

            return response()->json([
                'message' => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile(),
            ]);
        }
    }
}
