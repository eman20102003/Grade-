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
                "error" => "Please fill in all required fields before submitting."
            ]);
        }


        if (!preg_match('/https:\/\/docs\.google\.com\/spreadsheets\/d\//', $sheet1)) {
            return response()->json([
                "success" => false,
                "error" => "Please enter a valid Google Sheets link for the input data."
            ]);
        }

        if (!preg_match('/https:\/\/docs\.google\.com\/spreadsheets\/d\//', $sheet2)) {
            return response()->json([
                "success" => false,
                "error" => "Please provide an editable Google Sheets link for the output file."
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

            $response = Http::timeout(120)->retry(2, 1000)->post('http://127.0.0.1:5678/webhook-test/grade', [
                "sheet1" => $sheet1,
                "sheet2" => $sheet2,
                "prompt" => $prompt
            ]);


            if (!$response->successful()) {
                return response()->json([
                    "success" => false,
                    //"error" => "n8n error: " . $response->body()
                    "error" => "We couldn't process the data at the moment. Please check your input files and try again later."
                ]);
            }

            $data = $response->json();

              if (!is_array($data)) {
                   return response()->json([
                  "success" => false,
                  "error" => "Invalid response from processing server"
               ]);
             }

             $sheetUrl = $data['sheet_url'] ?? null;

              if (!$sheetUrl) {
                   return response()->json([
                   "success" => false,
                  "error" => "Result sheet was not generated properly"
               ]);
            }

                return response()->json([
                       "success" => true,
                       "sheet_url" => $sheetUrl,
                       "avg" => $data['avg'] ?? null,
                       "max" => $data['max'] ?? null,
                       "min" => $data['min'] ?? null,
                    "explanation" => $data['explanation'] ?? null,
             ]);
        } catch (\Exception $e) {

            return response()->json([
                "success" => false,
                //"error" => $e->getMessage()
                "error" => "Something went wrong while processing your request. Please try again."
            ]);
        }
    }
}
