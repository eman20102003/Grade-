<?php
namespace App\Jobs;

use App\Models\GradingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessGradingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 1;          
    public $backoff = 30;         

    public function retryUntil(): \DateTime
      {
         return now()->addMinutes(20);
    }

    public function failed(\Throwable $e): void
      {
         GradingJob::find($this->jobId)?->update([
            'status' => 'failed',
            'result' => ['error' => $e->getMessage()],
        ]);
      }

    public function __construct(
        public string       $jobId,
        public string       $sheet1,
        public string       $sheet2,
        public string       $prompt,
        public array|null   $analysis   //  nullable frontend may not have analyzed yet
    ) {}

    public function handle(): void
    {
        try {
            $response = Http::timeout(600)
              ->connectTimeout(15) 
                ->post(env('N8N_WEBHOOK_URL'), [
                    'sheet1'   => $this->sheet1,
                    'sheet2'   => $this->sheet2,
                    'prompt'   => $this->prompt,
                    'analysis' => $this->analysis ?? [],
                ]);

            $data = $response->json();

            \Log::info('n8n response', [
                 'status' => $response->status(),
                 'data'   => $data,
              ]);

           $success = $response->successful() && isset($data['sheet_url']);
$errorMessage = 'Processing failed in workflow.';
if (!$success) {
    $body = $response->body();
    if (!empty($data['message'])) {
        $errorMessage = $data['message'];
    } elseif (!empty($data['error'])) {
        $errorMessage = $data['error'];
    } elseif (
        $response->status() === 403 ||
        str_contains($body, 'PERMISSION_DENIED') ||
        str_contains($body, 'insufficientPermissions') ||
        str_contains($body, 'The caller does not have permission')
    ) {
        $errorMessage = 'Your output sheet is not editable. Please open the sheet → click Share → change to "Anyone with the link can Edit".';
    } elseif (preg_match('/Problem in node[^\n]*\n(.*?)(?:\[|$)/s', $body, $matches)) {
        $errorMessage = trim($matches[1]);
    } elseif (preg_match('/"message"\s*:\s*"([^"]+)"/i', $body, $matches)) {
        $errorMessage = trim($matches[1]);
    } elseif (!isset($data['sheet_url'])) {
        $errorMessage = 'Output sheet could not be written. Please make sure it is shared as "Anyone with the link can Edit".';
    }
}

GradingJob::find($this->jobId)->update([
    'status' => $success ? 'done' : 'failed',
    'result' => $success ? $data : ['error' => $errorMessage],
]);

        } catch (\Exception $e) {
            GradingJob::find($this->jobId)->update([
                'status' => 'failed',
                'result' => ['error' => $e->getMessage()],
            ]);
        }
    }
}