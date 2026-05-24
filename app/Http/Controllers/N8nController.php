<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\GradingJob;
use App\Jobs\ProcessGradingJob;
class N8nController extends Controller
{
    //  Send to n8n 
public function send(Request $request)
{
    $sheet1   = $request->sheet1;
    $sheet2   = $request->sheet2;
    $prompt   = $request->prompt;
    $analysis = $request->analysis;

    if (!$sheet1 || !$sheet2 || !$prompt || !$analysis) {
        return $this->error('Please fill in all required fields before submitting.');
    }

    if (!$this->isValidSheetUrl($sheet1)) {
        return $this->error('Please enter a valid Google Sheets link for the input data.');
    }

    if (!$this->isValidSheetUrl($sheet2)) {
        return $this->error('Please provide an editable Google Sheets link for the output file.');
    }

    if (!str_contains($sheet2, '/edit')) {
        return $this->error('Output sheet must be an edit link.');
    }

    if (str_contains($sheet1, '/edit')) {
        $sheet1 = explode('/edit', $sheet1)[0] . '/gviz/tq?tqx=out:csv&gid=0';
    }

   $jobId    = Str::uuid()->toString();
$analysis = is_array($request->analysis) ? $request->analysis : null; // ← normalize here

GradingJob::create(['id' => $jobId, 'status' => 'pending']);
ProcessGradingJob::dispatch($jobId, $sheet1, $sheet2, $prompt, $analysis);

return response()->json(['success' => true, 'job_id' => $jobId]);
}

public function jobStatus(string $jobId)
{
    $job = GradingJob::find($jobId);
    if (!$job) return response()->json(['status' => 'not_found'], 404);
  


   
    if (
        $job->status === 'pending' &&
        $job->created_at->diffInMinutes(now()) > 15
    ) {
        $job->update(['status' => 'failed', 'result' => ['error' => 'Timed out']]);
        return response()->json(['status' => 'failed']);
    }



    if ($job->status === 'done') {
        $data = $job->result;
        return response()->json([
            'status'      => 'done',
            'sheet_url'   => $data['sheet_url']   ?? null,
            'avg'         => $data['avg']          ?? null,
            'max'         => $data['max']          ?? null,
            'min'         => $data['min']          ?? null,
            'explanation' => $data['explanation']  ?? null,
        ]);
    }

    return response()->json(['status' => $job->status]); // pending أو failed
}
    //  Analyze Prompt 

    public function analyzePrompt(Request $request)
    {
        $request->validate([
            'sheet_url' => 'required|string',
            'prompt'    => 'required|string',
        ]);

        $sheetUrl = $request->input('sheet_url');
        $prompt   = $request->input('prompt');
        $sheetId  = $this->extractSheetId($sheetUrl);
        $gid      = $this->extractGid($sheetUrl) ?? '0';

        if (!$sheetId) {
            return response()->json(['success' => false, 'message' => 'Invalid Google Sheet URL'], 422);
        }

        if (strlen(trim($prompt)) < 10) {
            return response()->json([
                 'success' => false,
                 'message' => 'Please provide a more detailed grading prompt (at least 10 characters).'
            ], 422);
        }

        $csvUrl      = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
        $csvResponse = Http::timeout(60)->retry(3, 2000)->get($csvUrl);

        if ($csvResponse->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read Google Sheet. Make sure it is public or accessible.',
            ], 500);
        }

        $headers = $this->extractHeadersFromCsv($csvResponse->body());

        if (empty($headers)) {
            return response()->json(['success' => false, 'message' => 'No headers found in the first row of the sheet.'], 422);
        }

        $openAiResponse = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(120)
            ->retry(3, 3000, fn($exception, $response) =>
                $exception || ($response && in_array($response->status(), [500, 429, 503]))
            )
            ->post('https://api.openai.com/v1/responses', [
                'model' => 'gpt-4.1',
                'input' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => json_encode([
                        'user_prompt'       => $prompt,
                        'available_columns' => $headers,
                    ], JSON_UNESCAPED_UNICODE)],
                ],
            ]);

        if ($openAiResponse->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI request failed',
                'error'   => $openAiResponse->body(),
            ], 500);
        }

        $analysisText = $openAiResponse->json()['output'][0]['content'][0]['text'] ?? null;

        if (!$analysisText) {
            return response()->json(['success' => false, 'message' => 'No analysis returned from OpenAI.'], 500);
        }

        $analysis = json_decode($analysisText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI returned invalid JSON.',
                'raw'     => $analysisText,
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'headers'  => $headers,
            'analysis' => $analysis,
        ]);
    }

    //  Helpers 

    private function error(string $message, int $status = 200)
    {
        return response()->json(['success' => false, 'error' => $message], $status);
    }

    private function isValidSheetUrl(string $url): bool
    {
        return (bool) preg_match('/https:\/\/docs\.google\.com\/spreadsheets\/d\//', $url);
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

        if ($content === '') return [];

        $lines = preg_split("/\r\n|\n|\r/", $content);

        if (!$lines || empty($lines[0])) return [];

        $firstLine  = $lines[0];
        $delimiters = [
            ','  => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            ';'  => substr_count($firstLine, ';'),
            '|'  => substr_count($firstLine, '|'),
        ];

        arsort($delimiters);

        $delimiter = ',';
        $maxCount  = 0;

        foreach ($delimiters as $symbol => $count) {
            if ($count > $maxCount) {
                $delimiter = $symbol;
                $maxCount  = $count;
            }
        }

        if ($maxCount === 0) return [trim($firstLine)];

        $headers = str_getcsv($firstLine, $delimiter);

        Log::info('Headers:', $headers);

        return array_values(array_filter(array_map('trim', $headers), fn($h) => $h !== ''));
    }

    private function systemPrompt(): string
    {
        return <<<EOT
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
    }
}