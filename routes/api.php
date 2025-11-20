<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\Internal\DecryptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/data', [DataController::class, 'store']);
Route::patch('/data/{data_id}', [DataController::class, 'update']); // Assuming data_id is alphanumeric

Route::get('/redis-check', [DataController::class, 'redisCheck']);
Route::group(['prefix' => 'internal'], function () {

    // Perhatikan: Kita TIDAK menggunakan middleware 'api' di sini.
    // Keamanan ditangani oleh Bearer Token di Controller.
    Route::post('/decrypt', [DecryptController::class, 'decrypt']);
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
