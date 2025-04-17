<?php

namespace App\Jobs\Order;

use App\Jobs\Job;
use App\Models\ClientProfile;

use Carbon\Carbon;
use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchOrderBuyerInfoJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public $client_info;
    public $client_id;
    public $inactive_reports;
    public $sellerId;
    public $marketplaceId;
    public $purchase_daterange;
    public $orderId;
    public $rowsLimit;
    public $chunkSize;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($client_info, $sellerId = '', $marketplaceId = '', $purchase_daterange = '', $orderId = '', $rowsLimit = 400, $chunkSize = 200)
    {
        $this->client_info = $client_info;
        $this->client_id = $client_info['client_id'];
        $this->inactive_reports = $client_info['inactive_reports'];
        $this->sellerId = $sellerId;
        $this->marketplaceId = $marketplaceId;
        $this->purchase_daterange = $purchase_daterange;
        $this->orderId = $orderId;
        $this->chunkSize = $chunkSize;
        $this->rowsLimit = $rowsLimit;

        $this->onQueue(Q_SELLER_ORDER_BUYERINFO_DISPATCH);

        $this->switchToTestQueueIfTestServer();
    }

    /**
     * handle job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $offset = 0;
            do {
                list($sellerId_profile_mapping, $purchase_date_orders) = $this->getAmazonOrderIdsFromBQ($offset);

                foreach ($purchase_date_orders as $marketplaceId_sellerId => $purchase_date_order) {

                    Log::info("Amazon Order Ids Count [{$marketplaceId_sellerId}] -> " . count($purchase_date_order));
                    $orderChunks = createChunksOfData($purchase_date_order, $this->chunkSize);
                    foreach ($orderChunks as $orderChunk) {

                        $profile = $sellerId_profile_mapping[$marketplaceId_sellerId];

                        if ($profile !== false && !is_null($profile)) {

                            $profile_info = [
                                'client_id' => $this->client_id,
                                'profile_id' => $profile->id,
                                'marketplaceId' => $profile->marketplaceId,
                                'sellerId' => $profile->sellerId,
                                'client_authorisation_id' => $profile->client_authorisation_id
                            ];

                            Log::info("profile_info for GetOrderBuyerInfoJob()");
                            Log::info($profile_info);
                            $job = new GetOrderBuyerInfoJob($orderChunk, $profile_info);

                            if (!empty($queue_name)) {
                                $job->onQueue($queue_name);
                            }

                            $job->onConnection($this->connection);

                            dispatch($job);
                        }
                    }
                }

                $offset += $this->rowsLimit;

            } while (count($purchase_date_orders) > 0);

        } catch (\Exception $e) {
            Log::error($e);
            Log::error($e->getMessage());
        }

    }

    public function getAmazonOrderIdsFromBQ($offset)
    {
        $projectId = 'amazon-sp-report-loader';
        $dataset = 'orders';
        $table_name = 'flat_file_all_orders_data_by_order_date_general_' . $this->client_id;
        $buyerinfo_table_name = 'orders_buyer_info_' . $this->client_id;
        $bigQuery = new BigQueryClient([
            'projectId' => $projectId,
            'keyFilePath' => 'bq-credentials-sp.json',
        ]);

        $purchase_start_date_clause = "";
        $purchase_end_date_clause = "";

        if (!te_compare_strings($this->purchase_daterange, 'all')) {
            list($purchase_start_date, $purchase_end_date) = explode(",", $this->purchase_daterange . ',');
            if (!empty($purchase_start_date)) {
                $purchase_start_date = Carbon::parse($purchase_start_date)->toDateString();
                $purchase_start_date_clause = " AND DATE(purchase_date)>='{$purchase_start_date}'";
            }
            if (!empty($purchase_end_date)) {
                $purchase_end_date = Carbon::parse($purchase_end_date)->toDateString();
                $purchase_end_date_clause = " AND DATE(purchase_date)<='{$purchase_end_date}'";
            }
        }

        $sellerId_clause = empty($this->sellerId) ? "" : " AND sellerId='{$this->sellerId}'";
        $marketplace_clause = empty($this->marketplaceId) ? "" : " AND marketplace='{$this->marketplaceId}'";
        $amazon_order_id_clause = empty($this->orderId) ? "" : " AND amazon_order_id='{$this->orderId}'";

        $query = "SELECT DISTINCT marketplace,sellerId,DATE(purchase_date) AS purchase_date,amazon_order_id
                    FROM `{$projectId}.{$dataset}.{$table_name}`
                    WHERE 1=1
                    $purchase_start_date_clause $purchase_end_date_clause
                    $sellerId_clause $marketplace_clause $amazon_order_id_clause
                    AND amazon_order_id NOT IN (
                        SELECT DISTINCT AmazonOrderId FROM `{$projectId}.{$dataset}.{$buyerinfo_table_name}`
                    )
                    ORDER BY marketplace, sellerId, amazon_order_id
                    LIMIT {$this->rowsLimit} OFFSET {$offset}";

        Log::info($query);
        $jobConfig = $bigQuery->query($query);
        $queryResults = $bigQuery->runQuery($jobConfig);

        $purchase_date_orderIds = [];

        $marketplace_sellerId_mapping = [];

        foreach ($queryResults as $row) {

            $marketplace = $row['marketplace'];
            $sellerId = $row['sellerId'];
            $amazon_order_id = $row['amazon_order_id'];
            $purchase_date = $row['purchase_date'];

            if (!isset($marketplace_sellerId_mapping["{$marketplace}_{$sellerId}"])) {
                $clientProfile = ClientProfile::where("client_id", $this->client_id)
                    ->where("marketplaceId", $marketplace)
                    ->where("sellerId", $sellerId)
                    ->first();

                if (!$clientProfile) {
                    $clientProfile = false;
                }

                $marketplace_sellerId_mapping["{$marketplace}_{$sellerId}"] = $clientProfile;
            }

            if (!isset($purchase_date_orderIds["{$marketplace}_{$sellerId}"])) {
                $purchase_date_orderIds["{$marketplace}_{$sellerId}"] = [];
            }

            if ($marketplace_sellerId_mapping["{$marketplace}_{$sellerId}"] !== false) {
                $purchase_date_orderIds["{$marketplace}_{$sellerId}"][] = [$purchase_date, $amazon_order_id];
            }

        }

        return [$marketplace_sellerId_mapping, $purchase_date_orderIds];
    }

}
