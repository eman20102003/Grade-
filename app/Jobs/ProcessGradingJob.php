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
        if (isset($data['success']) && $data['success'] === false) {
              GradingJob::find($this->jobId)->update([
               'status' => 'failed',
               'result' => ['error' => $data['error'] ?? 'Processing failed. Please try again.'],
             ]);
         return;
       }

        $success = $response->successful() && isset($data['sheet_url']);

        if (!$success) {
            $errorMessage =
               $data['error']       ??
               $data[0]['error']    ??
               $data['message']     ??
               $data[0]['message']  ??
               'Processing failed. Please try again.';

            GradingJob::find($this->jobId)->update([
                'status' => 'failed',
                'result' => ['error' => $errorMessage],
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
}
}