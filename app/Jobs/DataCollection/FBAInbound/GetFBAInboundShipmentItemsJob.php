<?php

namespace App\Jobs\DataCollection\FBAInbound;

use App\Jobs\Job;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\S3Helper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

class GetFBAInboundShipmentItemsJob extends Job
{
    public $profile_info;
    public $updatedAfter;
    public $updatedBefore;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info, $updatedAfter, $updatedBefore)
    {
        $this->profile_info = $profile_info;

        $this->updatedAfter = $updatedAfter;
        $this->updatedBefore = $updatedBefore;

        $this->onQueue(Q_FBA_INBOUND_SHIPMENT_ITEM_GET);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {

        $amazonAPIClient = SpApiHelper::getFBAInboundV0ApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'], $this->profile_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('Get FBA Inbound Shipments Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
            return;
        }

        $json_output_file = public_path("downloads/tmp_fba_inbound_shipments_items_" . $this->profile_info['profile_id'] . "__" . date("Ymd_His") . "_" . rand(0, 99999) . "_" . time() . ".json");
        $apiResp = null;

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

                    $apiRetry = false;
                    $apiRetryCount++;

                    try {

                        if ($pagination > 1) {
                            Log::info("Waiting for {" . DELAY_FBA_INBOUND_SHIPMENT_GET . "} sec after Get inbound shipments...");
                            _sleep(DELAY_FBA_INBOUND_SHIPMENT_GET, 'seconds');
                        }

                        Log::info("Getting FBA Inbound Shipment Items (" . ($pagination) . ") -> updatedAfter [{$this->updatedAfter}], updatedBefore [{$this->updatedBefore}] , nextToken [" . (is_null($nextToken) ? "null" : "##########") . "]");

                        $updatedAfter = DateHelper::formatDateISO8601($this->updatedAfter);
                        $updatedBefore = DateHelper::formatDateISO8601($this->updatedBefore, true);

                        $updatedAfter = new \DateTime($updatedAfter); // convert only if it’s a string
                        $updatedBefore = new \DateTime($updatedBefore); // convert only if it’s a string

                        $queryType = is_null($nextToken) ? "DATE_RANGE" : "NEXT_TOKEN";

                        $response = $amazonAPIClient->getShipmentItems(
                            $queryType,
                            $this->profile_info['marketplaceId'],
                            $updatedAfter,
                            $updatedBefore,
                            $nextToken
                        );
                        $apiResp = $response->json();

                    } catch (TooManyRequestsException $e) {
                        Log::error("TooManyRequestsException");
                        Log::error($e->getMessage());
                        $apiRetry = true;
                        $sleepTime = calculateIncrementalSleepTime(DELAY_FBA_INBOUND_SHIPMENT_GET, $apiRetryCount);
                        Log::info("Waiting for $sleepTime secs...");
                        sleep($sleepTime);
                    } catch (\Exception $e) {

                        Log::error("Exception");
                        Log::error($e);
                        $apiResp = null;

                        if (stripos($e->getMessage(), "Too Many Requests") !== false || stripos($e->getMessage(), "QuotaExceeded") !== false) {
                            $apiRetry = true;
                            $sleepTime = calculateIncrementalSleepTime(DELAY_FBA_INBOUND_SHIPMENT_GET, $apiRetryCount);
                            Log::info("Waiting for $sleepTime secs...");
                            sleep($sleepTime);
                        } else {
                            $apiRetry = false;
                        }
                    }
                } while ($apiRetryCount < $apiMaxTries && $apiRetry);

            } while ($pollRetry && $pollTryCount <= $pollMaxTries);

            $nextToken = @$apiResp['payload']['NextToken'];
            $nextToken = empty($nextToken) ? null : $nextToken;

            $isLastChunk = is_null($nextToken);
            $data = @$apiResp['payload']["ItemData"];
            $pagination++;
            $ndJson = $this->convertArrayIntoNDJSON($data, $isLastChunk);

            file_put_contents($json_output_file, $ndJson, FILE_APPEND);

        } while (!is_null($nextToken));

        $fileDownloadedFromAmazonSuccessfully = file_exists($json_output_file);
        if ($fileDownloadedFromAmazonSuccessfully) {
            Log::info("FBA Inbound Shipment Items Data Written on JSON File successfully -> " . $json_output_file);
            $this->uploadToS3($json_output_file);
        }

    }

    public function convertArrayIntoNDJSON($data, $isLastChunk)
    {
        return implode(
                "\n",
                array_map(function ($item) {

                    $_item = [
                        'ShipmentId' => $item['ShipmentId'] ?? '',
                        'SellerSKU' => $item['SellerSKU'] ?? '',
                        'FulfillmentNetworkSKU' => $item['FulfillmentNetworkSKU'] ?? '',
                        'QuantityShipped' => $item['QuantityShipped'] ?? 0,
                        'QuantityReceived' => $item['QuantityReceived'] ?? 0,
                        'QuantityInCase' => $item['QuantityInCase'] ?? 0,
//                        'PrepDetailsList' => $item['PrepDetailsList'] ?? []
                    ];

                    return json_encode($_item);
                }, $data)
            ) . ($isLastChunk ? '' : "\n");
    }

    public function uploadToS3($localFilePath)
    {
        $reportDetails = $this->profile_info;
        $reportDetails['reportType'] = FBA_INBOUND_SHIPMENT_ITEM;
        $reportDetails['startDate'] = $this->updatedAfter;
        $reportDetails['endDate'] = $this->updatedBefore;
        $reportDetails['reportOptions'] = null;
        list($s3filePrefix, $s3fileName) = getReportPrefixAndNameForS3($reportDetails);
        S3Helper::uploadFileToS3($localFilePath, "$s3filePrefix/$s3fileName");
        te_delete_file($localFilePath);
    }
}
