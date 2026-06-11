<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\N8nController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/n8n-error', [N8nController::class, 'storeError']);
Route::get('/n8n-last-error', function () {
    return response()->json([
        'success' => true,
        'error' => \Illuminate\Support\Facades\Cache::get('n8n_last_error')
    ]);
});