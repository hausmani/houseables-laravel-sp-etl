<?php

namespace App\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ReUploadS3FilesJob extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $bucket;
    public $prefix;
    public $delay;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bucket, $prefix, $delay)
    {
        $this->bucket = $bucket;
        $this->prefix = $prefix;
        $this->delay = $delay;

        $this->onQueue(Q_DEFAULT);
        $this->switchToTestQueueIfTestServer();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $file_name = "s3_file_paths_" . time() . "_" . rand(0, 9999) . ".txt";
        $file_path = public_path("downloads/$file_name");
        $get_s3_files = "aws s3 ls --recursive s3://{$this->bucket}/{$this->prefix} | awk '{print $4}' > $file_path";
        exec($get_s3_files);

        if (file_exists($file_path)) {
            $this->triggerLambdas($file_path);
        }

    }

    public function triggerLambdas($file_path)
    {
        $ifp = fopen($file_path, 'r');
        $file_prefixes = [];
        while (!feof($ifp)) {
            $row = fgets($ifp);
            $row = trim(preg_replace('/\s\s+/', ' ', $row));
            if (!empty($row)) {
                $file_prefixes[] = $row;
            }
        }

        if (count($file_prefixes) == 0) {
            Log::warning(" -------------> No Prefix Found Under {$this->prefix}");
        }

        foreach ($file_prefixes as $s3_prefix) {
            $command = "aws s3 cp s3://{$this->bucket}/{$s3_prefix} s3://{$this->bucket}/{$s3_prefix} --metadata-directive REPLACE ";
            Log::info("Running --> " . $command);
            exec($command);
            sleep($this->delay);
        }

        te_delete_file($file_path);

    }
}
