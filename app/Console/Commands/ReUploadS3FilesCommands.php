<?php

namespace App\Console\Commands;

use App\Jobs\ReUploadS3FilesJob;
use Illuminate\Console\Command;

class ReUploadS3FilesCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reupload:s3:files
    {--bucket= : S3 Bucket}
    {--prefix= : S3 Prefix}
    {--delay= : File Copy Delay}
    {--conn= : queue connection}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This job will trigger lambda functions for already uploaded s3 files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $bucket = $this->option('bucket') ? $this->option('bucket') : '';
        $prefix = $this->option('prefix') ? $this->option('prefix') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';
        $delay = $this->option('delay') ? $this->option('delay') : '';
        $delay = $delay == 'none' ? 0 : (empty($delay) ? 1 : $delay);

        if (empty($bucket) || empty($prefix)) {
            $this->error("bucket and prefix should not be empty.");
            return;
        }

        $this->info("re-uploading s3 files ->  {$bucket}/{$prefix}");
        $job = new ReUploadS3FilesJob($bucket, $prefix, $delay);
        if (!empty($conn)) {
            $job->onConnection($conn);
        }
        dispatch($job);
    }

}
