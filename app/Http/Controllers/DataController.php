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
        $validFields = $application->fields()->pluck('name')->toArray();
        $incomingData = $request->all();
        //dd($validFields);
        $filteredData = array_intersect_key($incomingData, array_flip($validFields));
        //dd(print_r($filteredData));
        
        //dd($application);
        $id = $application->getAttributes()['id'];
        $streamKey = "app:{$id}:stream";
        //dd($streamKey);
        $MessageId = Redis::command(
            'xadd',
            [
                $streamKey,
                '*',
                $filteredData,
            ]
        );

        return response()->json([
            'message' => 'Data received and queued',
            'message_id' => $MessageId
        ]);
    }
}
