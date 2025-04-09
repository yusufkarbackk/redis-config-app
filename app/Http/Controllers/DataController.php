<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

use function PHPSTORM_META\type;

class DataController extends Controller
{
    public function store(Request $request)
    {

        //validate API key

        $apiKey = $request->header('X-API-Key');
        //dd($apiKey);
        $application = Application::where('api_key', $apiKey)->select(['name', 'api_key', 'id'])->firstOrFail();
        //dd($application);
        //validate incoming fields against configured fields
        $validFields = $application->applicationFields()->pluck('name')->toArray();
        //dd($validFields);
        //$filteredData = array_intersect_key($incomingData, array_flip($validFields));

        $id = $application->getAttributes()['api_key'];
        $streamKey = "app:{$id}:stream";
        // dd($streamKey);

        $filteredData = $request->only($validFields);
        //dd($filteredData);
        try {
            $MessageId = Redis::command(
                'xadd',
                [
                    $streamKey,
                    '*',
                    $filteredData,
                ]
            );

            //dd($MessageId);

            // $log->log = "send data to Redis Server";
            // $log->save();

            //dd($MessageId);
            return response()->json([
                'message' => 'Data received and queued',
                'message_id' => $MessageId,
                "data" => $filteredData

            ]);
        } catch (\Throwable $th) {
            // $log->log = $th->getMessage();
            // $log->save();
            return response()->json([
                'message' => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile()
            ]);
        }
    }
}
