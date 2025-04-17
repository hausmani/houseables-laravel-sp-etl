<?php

namespace App\Jobs\Order;

use App\Jobs\Job;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\S3Helper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetOrderBuyerInfoJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $orderIds;
    protected $profile_id;
    protected $client_id;
    protected $oauth_id;
    protected $sellerId;
    protected $marketplaceId;

    public function __construct($orderIds, $profile_info)
    {
        $this->orderIds = $orderIds;
        $this->profile_id = $profile_info['profile_id'];
        $this->client_id = $profile_info['client_id'];
        $this->oauth_id = $profile_info['client_authorisation_id'];
        $this->sellerId = $profile_info['sellerId'];
        $this->marketplaceId = $profile_info['marketplaceId'];

        $this->onQueue(Q_SELLER_ORDER_BUYERINFO_GET_API);
    }

    /**
     * handle job.
     *
     * @return void
     */
    public function handle()
    {
        $this->getBuyerDetails();
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    public function getBuyerDetails()
    {

        $orderApiClient = SpApiHelper::getOrdersV0ApiClient($this->profile_id, $this->oauth_id, $this->marketplaceId);

        if (!$orderApiClient) {
            Log::error("Failed to initialize OrdersV0Api client");
            return;
        }

        foreach ($this->orderIds as $purchase_date_orderId) {
            $purchase_date = $purchase_date_orderId[0];
            $orderId = $purchase_date_orderId[1];
            $responseData = false;

            $retryCount = 1;
            $maxRetries = 15;
            do {
                $retry = false;
                try {
                    Log::info("Getting Order Buyer Info for orderId -> {$orderId}");
                    $report = $orderApiClient->getOrderBuyerInfo($orderId);
                    $responseData = $report->payload; // Adjust as per your actual response structure
                    $retryCount++;
                } catch (\Exception $e) {
                    Log::error("Error fetching buyer information for Order ID $orderId: [" . $e->getMessage() . ']');
                    $responseData = false;
                    if ($e->getCode() == 429) {
                        $retry = true;
                        Log::info("Rate limit hit for orderId {$orderId}, waiting before retrying ({$retryCount})...");
                        sleep(4 * $retryCount);
                    }
                }

            } while ($retry && $retryCount <= $maxRetries);

            if ($responseData == false) {
                // all retries failed.
                // notify bugsnag etc
            } else {
                $this->storeInS3($responseData, $purchase_date);
            }
        }
    }

    public function storeInS3($responseData, $purchase_date)
    {
        if (empty($responseData)) {
            Log::warning("No data to store.");
            return;
        }

        $order_id = $responseData['amazon_order_id'];

        $timestamp = time();
        $localFilePath = public_path("downloads/{$purchase_date}_{$order_id}_{$this->client_id}_{$this->sellerId}_{$this->marketplaceId}_{$timestamp}.json");
        file_put_contents($localFilePath, $responseData);

        $s3_date = DateHelper::changeDateFormat($purchase_date, "Y-m-d", 'Ymd');

        $countryCode = getMarketplaceInfo('marketplaceId', $this->marketplaceId, 'countryCode');
        $prefix = "amazon-selling-partners-api/" . ORDERS_BUYER_INFO . "/$countryCode/{$this->client_id}/{$this->sellerId}/purchase_date={$s3_date}";
        $s3Path = "{$prefix}/{$order_id}.json";

        S3Helper::uploadFileToS3($localFilePath, $s3Path);

        te_delete_file($localFilePath);

    }
}
