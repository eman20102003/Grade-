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
                'job_id'   => $this->jobId,
            ]);

        $raw = $response->json();

        \Log::info('n8n RAW response', [
            'status' => $response->status(),
            'raw'    => $raw,
            'body'   => $response->body(),
        ]);

        $data = is_array($raw) && isset($raw[0]) ? $raw[0] : $raw;

        if (isset($data['success']) && $data['success'] === false) {
            GradingJob::find($this->jobId)->update([
                'status' => 'failed',
                'result' => ['error' => $data['error'] ?? 'Processing failed. Please check your prompt,data and try again.'],
            ]);
            return;
        }

        if (!$response->successful() || !isset($data['sheet_url'])) {
            GradingJob::find($this->jobId)->update([
                'status' => 'failed',
                'result' => ['error' => $data['error'] ?? $data['message'] ?? 'Processing failed. Please check your prompt,data and try again.'],
            ]);
            return;
        }

        GradingJob::find($this->jobId)->update([
            'status' => 'done',
            'result' => $data,
        ]);

    } catch (\Exception $e) {
        GradingJob::find($this->jobId)->update([
            'status' => 'failed',
            'result' => ['error' => $e->getMessage()],
        ]);
    }
}}