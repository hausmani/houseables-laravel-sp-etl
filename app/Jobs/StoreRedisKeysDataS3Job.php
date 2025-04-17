<?php

namespace App\Jobs;

use App\TE\HelperClasses\MyRedis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StoreRedisKeysDataS3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $keys;

    /**
     * Create a new job instance.
     *
     * @param array $keys
     * @return void
     */
    public function __construct(array $keys)
    {
        $this->keys = $keys;
        $this->load($this->keys);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function load($keys)
    {

        foreach ($keys as $key) {
            $json = MyRedis::redis_fetch_set_values($key);

            $bucket = env('AWS_BUCKET');

            $prefix = "amazon-selling-partners-api/ORDERS_Redis_Buyer_Order_Ids/{$key}/AD4JTYB7A7HOQ/purchase_date=20240606/";

            // Generate a file name for the JSON data
            $fileName = "{$key}.json";

            StoreRedisJasonJob::dispatch($json, $bucket, $prefix, $fileName);

        }

    }
}
