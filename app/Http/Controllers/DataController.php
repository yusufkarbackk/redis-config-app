<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

use function PHPSTORM_META\type;

class DataController extends Controller
{
    public function store(Request $request)
    {
        //validate API key

        $apiKey = $request->header('X-API-Key');
        $application = Application::where('api_key', $apiKey)->firstOrFail();
        //validate incoming fields against configured fields
        $validFields = $application->applicationFields()->pluck('name')->toArray();
        $incomingData = $request->all();

        $filteredData = array_intersect_key($incomingData, array_flip($validFields));

        $id = $application->getAttributes()['api_key'];
        $streamKey = "app:{$id}:stream";

        $MessageId = Redis::command(
            'xadd',
            [   
                $streamKey,
                '*',
                $filteredData,
            ]
        );

        //dd($MessageId);
        return response()->json([
            'message' => 'Data received and queued',
            'message_id' => $MessageId,
            
        ]);
    }
}
