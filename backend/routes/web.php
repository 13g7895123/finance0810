<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Finance CRM Backend API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString()
    ]);
});

// Broadcasting authentication route
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');