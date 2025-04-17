<?php

namespace App\Jobs\DataCollection\AWD;

use App\Jobs\Job;
use App\TE\HelperClasses\S3Helper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

class ListInventoryJob extends Job
{
    public $profile_info;

    public $sku;
    public $sortOrder;
    public $details;
    public $maxResults;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info)
    {
        $this->profile_info = $profile_info;

        $this->sku = null;
        $this->sortOrder = 'ASCENDING';
        $this->details = 'SHOW';
        $this->maxResults = 200;

        $this->onQueue(Q_AWD_LIST_INVENTORY);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {
        $amazonAPIClient = SpApiHelper::getAWDApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'], $this->profile_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('List AWD Inventory Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
            return;
        }

        $json_output_file = public_path("downloads/tmp_awd_inventory_" . $this->profile_info['profile_id'] . "__" . date("Ymd_His") . "_" . rand(0, 99999) . "_" . time() . ".json");

        $listInventoryResp = null;

        $nextToken = null;
        $pagination = 1;

        do {

            $pollTryCount = 0;
            $pollMaxTries = 7;
            do {

                $pollTryCount++;
                $pollRetry = false;

                $apiMaxTries = 10;
                $apiRetryCount = 0;

                do {

                    $listInventoryRetry = false;
                    $apiRetryCount++;

                    try {

                        if ($pagination > 1) {
                            Log::info("Waiting for {" . DELAY_AWD_LIST_INVENTORY . "} sec after AWD List Inventory...");
                            _sleep(DELAY_AWD_LIST_INVENTORY, 'seconds');
                        }

                        Log::info("Listing Inventory (" . ($pagination) . ") -> SKU [{$this->sku}], sortOrder [{$this->sortOrder}], details [{$this->details}], nextToken [{$nextToken}], maxResults [{$this->maxResults}]");
                        $response = $amazonAPIClient->listInventory($this->sku, $this->sortOrder, $this->details, $nextToken, $this->maxResults);
                        $listInventoryResp = $response->json();

                    } catch (TooManyRequestsException $e) {
                        Log::error($e->getMessage());
                        $listInventoryRetry = true;
                        $sleepTime = calculateIncrementalSleepTime(DELAY_AWD_LIST_INVENTORY, $apiRetryCount);
                        Log::info("Waiting for $sleepTime secs...");
                        sleep($sleepTime);
                    } catch (\Exception $e) {

                        $listInventoryResp = null;

                        if (stripos($e->getMessage(), "Too Many Requests") !== false || stripos($e->getMessage(), "QuotaExceeded") !== false) {
                            $listInventoryRetry = true;
                            $sleepTime = calculateIncrementalSleepTime(DELAY_AWD_LIST_INVENTORY, $apiRetryCount);
                            Log::info("Waiting for $sleepTime secs...");
                            sleep($sleepTime);
                        } else {
                            $listInventoryRetry = false;
                        }
                    }
                } while ($apiRetryCount < $apiMaxTries && $listInventoryRetry);

            } while ($pollRetry && $pollTryCount <= $pollMaxTries);

            $nextToken = @$listInventoryResp['nextToken'];
            $nextToken = empty($nextToken) ? null : $nextToken;

            $isLastChunk = is_null($nextToken);

            $inventory = @$listInventoryResp['inventory'];
            $pagination++;
            $ndJson = $this->convertArrayIntoNDJSON($inventory, $isLastChunk);

            file_put_contents($json_output_file, $ndJson, FILE_APPEND);

        } while (!is_null($nextToken));

        $fileDownloadedFromAmazonSuccessfully = file_exists($json_output_file);
        if ($fileDownloadedFromAmazonSuccessfully) {

            Log::info("Inventory Data Written on JSON File successfully -> " . $json_output_file);
            $this->uploadToS3($json_output_file);

        }

    }

    public function convertArrayIntoNDJSON($inventory, $isLastChunk)
    {
        # {"inventoryDetails":{"availableDistributableQuantity":0,"replenishmentQuantity":5,"reservedDistributableQuantity":0},"sku":"0S-8VEX-VKHV-FBA","totalInboundQuantity":720,"totalOnhandQuantity":0}
        return implode(
                "\n",
                array_map(function ($item) {

                    $inventory_item = [
                        'sku' => $item['sku'] ?? '',
                        'totalInboundQuantity' => $item['totalInboundQuantity'] ?? 0,
                        'totalOnhandQuantity' => $item['totalOnhandQuantity'] ?? 0,
                        'availableDistributableQuantity' => $item['inventoryDetails']['availableDistributableQuantity'] ?? 0,
                        'replenishmentQuantity' => $item['inventoryDetails']['replenishmentQuantity'] ?? 0,
                        'reservedDistributableQuantity' => $item['inventoryDetails']['reservedDistributableQuantity'] ?? 0,
                    ];

                    return json_encode($inventory_item);
                }, $inventory)
            ) . ($isLastChunk ? '' : "\n");
    }

    public function uploadToS3($localFilePath)
    {
        $reportDetails = $this->profile_info;
        $reportDetails['reportType'] = AWD_INVENTORY;
        $reportDetails['startDate'] = date("Y-m-d");
        $reportDetails['endDate'] = date("Y-m-d");
        $reportDetails['reportOptions'] = null;
        list($s3filePrefix, $s3fileName) = getReportPrefixAndNameForS3($reportDetails);
        S3Helper::uploadFileToS3($localFilePath, "$s3filePrefix/$s3fileName");
        te_delete_file($localFilePath);
    }
}
