<?php

namespace App\Console\Commands\Order;

use App\Jobs\Order\DispatchOrderBuyerInfoJob;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DownloadOrdersBuyerInfoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:orders:buyerinfo
        {--cid= : DB Client IDs comma separated}
        {--pid= : DB Profile IDs comma separated}
        {--sid= : DB Seller IDs comma separated}
        {--mid= : DB marketplace IDs comma separated}
        {--oid= : Amazon Order Id comma separated}
        {--purchase_daterange= : Orders Purchase Date Range }
        {--rows_limit= : Orders Ids Rows Limit to query at once from BQ tbale }
        {--chunk_size= : Orders Ids chunk size }
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for downloading orders buyer info data for channels from API';

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

        $client_id = $this->option('cid') ? $this->option('cid') : '';
        $sellerId = $this->option('sid') ? $this->option('sid') : '';
        $marketplaceId = $this->option('mid') ? $this->option('mid') : '';
        $profile_id = $this->option('pid') ? $this->option('pid') : '';
        $rows_limit = $this->option('rows_limit') ? $this->option('rows_limit') : 400;
        $chunk_size = $this->option('chunk_size') ? $this->option('chunk_size') : 200;
        $purchase_daterange = $this->option('purchase_daterange') ? $this->option('purchase_daterange') : '';
        if (empty($purchase_daterange)) {
            $purchase_daterange = Carbon::now()->subDays(7)->format("Ymd") . ',' . Carbon::yesterday()->format("Ymd");
        }

        $order_id = $this->option('oid') ? $this->option('oid') : '';

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        $profile_types = parseProfileTypeArg(PROFILE_SELLER_CENTRAL);

        foreach ($profile_types as $profile_type) {

            $clientsForDataDownload = getClientsForDataDownload($client_id, $profile_type, $profile_id);
            foreach ($clientsForDataDownload as $client) {

                $client_id = $client->client_id;
                $client_info = [
                    'client_id' => $client_id,
                    'inactive_reports' => $client->inactive_reports,
                ];

                if (!empty($client->inactive_reports)) {
                    $inactive_reports = json_decode($client->inactive_reports, true);
                    if (iCheckInArray(ORDERS_BUYER_INFO, $inactive_reports) != -1) {
                        $this->info("Skipping INACTIVE Report [" . ORDERS_BUYER_INFO . "] for Client [{$client_id}]");
                        Log::info("Skipping INACTIVE Report [" . ORDERS_BUYER_INFO . "] for Client [{$client_id}]");
                        continue;
                    }
                }

                $this->info('Running [download:orders:buyerinfo] command for type ' . $profile_type . ' and client ' . $client_id);
                Log::info('Running [download:orders:buyerinfo] command for type ' . $profile_type . ' and client ' . $client_id);

                $job = new DispatchOrderBuyerInfoJob($client_info, $sellerId, $marketplaceId, $purchase_daterange, $order_id, $rows_limit, $chunk_size);

                if (!empty($queue_name)) {
                    $job->onQueue($queue_name);
                }

                if (!empty($conn)) {
                    $job->onConnection($conn);
                }

                dispatch($job);

            }
        }
    }
}
