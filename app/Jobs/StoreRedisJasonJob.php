<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\Storage\StorageClient;

class StoreRedisJasonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($json, $bucket, $prefix, $fileName)
    {
        $this->json = $json;
        $this->bucket = $bucket;
        $this->prefix = $prefix;
        $this->fileName = $fileName;
        $this->handle();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $localFilePath = storage_path('app/public/' . $this->fileName);

        $Json = json_encode($this->json, JSON_PRETTY_PRINT);

        file_put_contents($localFilePath, $Json);

//        $s3Path = "{$this->prefix}{$this->fileName}";

//        $command = "aws s3api put-object --bucket {$this->bucket} --key {$s3Path} --body {$localFilePath}";
//
//        exec($command);
//
//        // Google Cloud Storage settings
//        $projectId = 'your-project-id';
//        $bucketName = $this->bucket;  // Assuming $this->bucket contains the GCS bucket name
//
//        $storage = new StorageClient([
//            'projectId' => $projectId,
//        ]);
//
//        $bucket = $storage->bucket($bucketName);
//        $object = $bucket->upload(fopen($localFilePath, 'r'), [
//            'name' => "{$this->prefix}{$this->fileName}",
//        ]);

        if (file_exists($localFilePath)) {
            unlink($localFilePath);
        }
    }
}
