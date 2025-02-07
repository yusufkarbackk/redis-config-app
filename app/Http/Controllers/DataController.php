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
        $log = new Log();

        //validate API key

        $apiKey = $request->header('X-API-Key');
        $application = Application::where('api_key', $apiKey)->select(['api_key', 'id'])->firstOrFail();
        //validate incoming fields against configured fields
        $validFields = $application->applicationFields()->pluck('name')->toArray();

        //$filteredData = array_intersect_key($incomingData, array_flip($validFields));

        $id = $application->getAttributes()['api_key'];
        $streamKey = "app:{$id}:stream";

        $filteredData = $request->only($validFields);

        try {
            $MessageId = Redis::command(
                'xadd',
                [
                    $streamKey,
                    '*',
                    $filteredData,
                ]
            );

            $log->log = "send data to Redis Server";
            $log->save();

            //dd($MessageId);
            return response()->json([
                'message' => 'Data received and queued',
                'message_id' => $MessageId,
                "data" => $filteredData

            ]);
        } catch (\Throwable $th) {
            $log->log = $th->getMessage() ;
            $log->save();
        }
    }
}
