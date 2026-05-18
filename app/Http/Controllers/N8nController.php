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
        $analysis = $request->analysis;

        //  تحقق من الحقول الفارغة
        if (!$sheet1 || !$sheet2 || !$prompt || !$analysis) {
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
                "prompt" => $prompt,
                "analysis" => $analysis,
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

    //  تحليل الـ prompt باستخدام OpenAI API

    public function analyzePrompt(Request $request)
    {
        $request->validate([
            'sheet_url' => 'required|string',
            'prompt' => 'required|string',
        ]);

        $sheetUrl = $request->input('sheet_url');
        $prompt = $request->input('prompt');

        $sheetId = $this->extractSheetId($sheetUrl);
        $gid = $this->extractGid($sheetUrl) ?? '0';

        if (!$sheetId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google Sheet URL'
            ], 422);
        }

        $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

        $csvResponse = Http::timeout(60)->retry(3, 2000)->get($csvUrl);

        if ($csvResponse->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read Google Sheet. Make sure it is public or accessible.'
            ], 500);
        }

        $headers = $this->extractHeadersFromCsv($csvResponse->body());

        if (empty($headers)) {
            return response()->json([
                'success' => false,
                'message' => 'No headers found in the first row of the sheet.'
            ], 422);
        }

        $systemPrompt = <<<EOT
You are a grading-rules and column-mapping parser.
You receive a grading prompt and an available_columns list extracted from a real spreadsheet.
Output ONLY valid JSON. No markdown, no explanations, no code.

STRICT RULES:
1. Analysis only. No calculations. No executable code.
2. Never fix user mistakes. Never resolve contradictions. Preserve them and report in warnings.
3. Include ONLY components explicitly mentioned in the grading prompt.
4. Use ONLY columns from available_columns. Never invent, assume, or rename columns.
5. Map columns by meaning. If uncertain, add to uncertain_columns. If missing, add to missing_columns. If no confident match, use null.
6. raw_max_score means the original maximum score before weighting.
7. Extract raw_max_score ONLY from explicit scoring statements such as "out of 10", "max 30", "from 30", "من 10", "من 30".
8. Never extract raw_max_score from column names. Column names may contain dates, durations, attempts, or metadata.
9. For grouped components, do not put post-selection totals in components.raw_max_score. Put item-level scores and post-selection totals inside calculation_rules.
10. final_weight means the percentage weight in the final grade.
11. Extract final_weight ONLY when explicitly stated as a percentage weight. Otherwise use null.
12. Never assume raw_max_score equals final_weight or vice versa.
13. If scale_to or converted_to is present and differs from final_weight, preserve both and add a warning with both values explicitly stated.
14. Absence or null-value conditions are determined at execution time from actual data. Do not add attendance/absence columns to missing_columns. Encode the condition in calculation_rules.
15. calculation_rules values MUST be objects. Use {} if none.
16. Put all detected logic inside calculation_rules as structured objects.
17. column_mapping must use a string for one column and an array for multiple columns.
18. If raw_max_score is null for an explicitly mentioned component, add it to missing_information.
19. Put missing grading details in missing_information.
20. Put missing columns in missing_columns.
21. Put uncertain mappings in uncertain_columns.
22. Put contradictions or ambiguities in warnings.

OUTPUT STRUCTURE:
{
  "summary": "",
  "components": {
    "Component Name": {
      "raw_max_score": null,
      "final_weight": null
    }
  },
  "column_mapping": {},
  "calculation_rules": {},
  "missing_information": [],
  "missing_columns": [],
  "uncertain_columns": [],
  "warnings": []
}
EOT;

        $userPayload = [
            'user_prompt' => $prompt,
            'available_columns' => $headers,
        ];

        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(120)
            ->retry(3, 3000,function ($exception, $response) {
        // أعد المحاولة عند exception أو عند 500/429
        return $exception || 
               ($response && in_array($response->status(), [500, 429, 503]));  })
            ->post('https://api.openai.com/v1/responses', [
                'model' => 'gpt-4.1',
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        if ($openAiResponse->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI request failed',
                'error' => $openAiResponse->body(),
            ], 500);
        }

        $data = $openAiResponse->json();

        $analysisText = $data['output'][0]['content'][0]['text'] ?? null;

        if (!$analysisText) {
            return response()->json([
                'success' => false,
                'message' => 'No analysis returned from OpenAI.'
            ], 500);
        }

        $analysis = json_decode($analysisText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI returned invalid JSON.',
                'raw' => $analysisText,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'headers' => $headers,
            'analysis' => $analysis,
        ]);
    }

    private function extractSheetId(string $url): ?string
    {
        preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);

        return $matches[1] ?? null;
    }

    private function extractGid(string $url): ?string
    {
        preg_match('/gid=([0-9]+)/', $url, $matches);

        return $matches[1] ?? null;
    }

    private function extractHeadersFromCsv(string $content): array
{
    $content = trim($content);

    if ($content === '') {
        return [];
    }

    $lines = preg_split("/\r\n|\n|\r/", $content);

    if (!$lines || empty($lines[0])) {
        return [];
    }

    $firstLine = $lines[0];

    $delimiters = [
        ','  => substr_count($firstLine, ','),
        "\t" => substr_count($firstLine, "\t"),
        ';'  => substr_count($firstLine, ';'),
        '|'  => substr_count($firstLine, '|'),
    ];

    arsort($delimiters);

    $delimiter = ',';
    $maxCount = 0;

    foreach ($delimiters as $symbol => $count) {
        if ($count > $maxCount) {
            $delimiter = $symbol;
            $maxCount = $count;
        }
    }

    if ($maxCount === 0) {
        return [trim($firstLine)];
    }

    $headers = str_getcsv($firstLine, $delimiter);
    \Log::info('Headers:', $headers);


    return array_values(array_filter(array_map('trim', $headers), function ($header) {
        return $header !== '';
    }));
}
}