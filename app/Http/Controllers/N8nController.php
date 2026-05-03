<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class N8nController extends Controller
{
    public function send(Request $request)
{
    $sheet1 = $request->sheet1;
    $sheet2 = $request->sheet2;
    $prompt = $request->prompt;

  
    if (!$sheet1 || !$sheet2 || !$prompt) {
        return response()->json([
            "success" => false,
            "error" => "All fields are required"
        ]);
    }

    
    if (!str_contains($sheet1, 'docs.google.com/spreadsheets')) {
        return response()->json([
            "success" => false,
            "error" => "Input must be a Google Sheet link"
        ]);
    }

    if (!str_contains($sheet2, 'docs.google.com/spreadsheets')) {
        return response()->json([
            "success" => false,
            "error" => "Output must be a Google Sheet link"
        ]);
    }

 
    if (!str_contains($sheet2, '/edit')) {
        return response()->json([
            "success" => false,
            "error" => "Output sheet must be an edit link"
        ]);
    }

   
    if (str_contains($sheet1, '/edit')) {
        $sheet1 = explode('/edit', $sheet1)[0] . '/export?format=csv&gid=0';
    }

    try {

        $response = Http::post('http://localhost:5678/webhook-test/grade', [
            "sheet1" => $sheet1,
            "sheet2" => $sheet2,
            "prompt" => $prompt
        ]);

        return response()->json([
            "success" => true,
            "message" => "Sent to n8n successfully",
            "n8n_response" => $response->json()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "error" => "Failed to connect to n8n"
        ]);
    }
}
}