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

        //  تحقق من الحقول الفارغة
        if (!$sheet1 || !$sheet2 || !$prompt) {
            return response()->json([
                "success" => false,
                "error" => "Please fill in all required fields before submitting."
            ]);
        }

        //  تحقق من طول الـ prompt
        if (strlen(trim($prompt)) < 10) {
            return response()->json([
                "success" => false,
                "error" => "Please provide a more detailed grading prompt (at least 10 characters)."
            ]);
        }

        //  تحقق من صحة رابط الـ input
        if (!preg_match('/https:\/\/docs\.google\.com\/spreadsheets\/d\//', $sheet1)) {
            return response()->json([
                "success" => false,
                "error" => "Please enter a valid Google Sheets link for the input data."
            ]);
        }

        //  تحقق من صحة رابط الـ output
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

        //  تحويل رابط الـ input لـ CSV
        if (str_contains($sheet1, '/edit')) {
            $sheet1 = explode('/edit', $sheet1)[0] . '/gviz/tq?tqx=out:csv&gid=0';
        }

        try {
            //  timeout أطول وURL من .env
            $response = Http::timeout(180)->retry(2, 2000)->post(env('N8N_WEBHOOK_URL'), [
                "sheet1" => $sheet1,
                "sheet2" => $sheet2,
                "prompt" => $prompt
            ]);

            if (!$response->successful()) {
                return response()->json([
                    "success" => false,
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
               // "warning" => $data['warning'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "error" => "Something went wrong while processing your request. Please try again."
            ]);
        }
    }
}
