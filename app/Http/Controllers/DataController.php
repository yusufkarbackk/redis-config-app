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

        $id = $application->getAttributes()['api_key'];
        $streamKey = "app:data:stream";

        $filteredData = $request->only($validFields);
        $filteredData['enqueued_at'] = Carbon::now()->toIso8601String();
        //dd($streamKey);
        try {
            $prodConn = Redis::connection();        // objek Illuminate\Redis…\Connection
            $client = $prodConn->client();

            $prodcfg = $client->getHost() . ':' . $client->getPort();
            \Log::info("Redis connection established: {$prodcfg}");
            $MessageId = $client->xadd(
                $streamKey,     // e.g. "app:data:stream"
                '*',
                $filteredData
            );
            //dd($client);
            //dd($MessageId);
            return response()->json([
                'message' => 'Data received and queued',
                'message_id' => $MessageId,
                "data" => $filteredData,
            ]);
        } catch (\Throwable $th) {

            return response()->json([
                'message' => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile(),
            ]);
        }
    }

    public function redisCheck()
    {
        try {
            // Perform a simple PING
            $pong = Redis::ping();

            // Optionally, grab a bit more info:
            $info = Redis::info();           // returns array of INFO output
            $keys = Redis::keys('*');        // list all keys in the current DB

            // 2) Optionally, ask who the current master is (Predis‐specific)
            $client = Redis::connection()->client();
            $params = $client->getConnection()->getParameters();
            $masterAddress = $params->host . ':' . $params->port;

            return response()->json([
                'status' => 'connected',
                'pong' => $pong,
                'info' => $info,
                'keys' => $keys,
                'current_master' => $masterAddress
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
