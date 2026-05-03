<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\N8nController;

Route::get('/', function () {
    return view('index');
});
Route::get('/form', function () {
    return view('form');
});
Route::post('/send-to-n8n', [N8nController::class, 'send']);
