<?php

namespace App\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ReloadS3ReportFileJob extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $bucket;
    public $prefixes;
    public $sleep_interval;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bucket, $prefixes, $sleep_interval = 2)
    {
        $this->bucket = $bucket;
        $this->prefixes = $prefixes;
        $this->sleep_interval = $sleep_interval;

        $this->onQueue(Q_SELLER_REPORT_DOWNLOAD_S3);
        $this->switchToTestQueueIfTestServer();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->prefixes as $prefix) {
            $command = "aws s3 cp s3://{$this->bucket}/{$prefix} s3://{$this->bucket}/{$prefix} --metadata-directive REPLACE ";
            Log::info($command);
            exec($command);

            if (!empty($this->sleep_interval)) {
                Log::info("sleeping for {$this->sleep_interval} sec...");
                sleep($this->sleep_interval);
            }
        }
    }
}
