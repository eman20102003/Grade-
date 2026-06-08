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

Route::post('/analyze-prompt', [N8nController::class, 'analyzePrompt'])->name('prompt.analyze');
Route::get('/job-status/{jobId}', [N8nController::class, 'jobStatus']);



// Route::get('/test', function () {
//     return view('formNew');
// });
