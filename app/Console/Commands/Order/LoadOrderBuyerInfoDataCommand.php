<?php

namespace App\Console\Commands\Order;

use App\Jobs\Order\LoadOrderBuyerInfoDataJob;
use App\TE\HelperClasses\MyRedis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LoadOrderBuyerInfoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'load:order:buyerinfo
        {--c= : DB Client IDs comma separated}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for loading orders buyer info data for clients from Redis';

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

        $client_id = $this->option('c') ? $this->option('c') : '';

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        $redis_keyword = "buyerinfo__*";

        if (!empty($client_id)) {
            $redis_keyword = "buyerinfo__{$client_id}_*";

        }

        $redis_keys = MyRedis::scan_keys($redis_keyword);
        Log::info($redis_keys);
        $this->info(count($redis_keys) . " redis keys found for order buyer info");
        foreach ($redis_keys as $redis_key) {

            $this->info("dispatching -> Load Order Buyer Info Data Job for redis_key --> [$redis_key]");
            $job = new LoadOrderBuyerInfoDataJob($redis_key);
            if (!empty($conn)) {
                $job->onConnection($conn);
            } else {
                $job->onConnection('sqs');
            }
            if (!empty($queue_name)) {
                $job->onQueue($queue_name);
            }
            dispatch($job);
            $this->info("dispatched -> Load Order Buyer Info Data Job for redis_key --> [$redis_key]");
        }
    }
}
