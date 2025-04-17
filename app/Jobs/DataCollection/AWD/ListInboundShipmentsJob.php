<?php

namespace App\Jobs\DataCollection\AWD;

use App\Jobs\Job;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\S3Helper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

class ListInboundShipmentsJob extends Job
{
    public $profile_info;

    public $updatedAfter;
    public $updatedBefore;
    public $sortBy;
    public $sortOrder;
    public $shipmentStatus;
    public $maxResults;

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

        $this->sortBy = 'UPDATED_AT';
        $this->sortOrder = 'DESCENDING';
        $this->maxResults = 200;
        $this->shipmentStatus = null;

        $this->onQueue(Q_AWD_LIST_INBOUND_SHIPMENT);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {

        $amazonAPIClient = SpApiHelper::getAWDApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'], $this->profile_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('List AWD List Inbound Shipment Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
            return;
        }

        $json_output_file = public_path("downloads/tmp_awd_inbound_shipment_" . $this->profile_info['profile_id'] . "__" . date("Ymd_His") . "_" . rand(0, 99999) . "_" . time() . ".json");
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
                            Log::info("Waiting for {" . DELAY_AWD_LIST_INVENTORY . "} sec after AWD List inbound shipments...");
                            _sleep(DELAY_AWD_LIST_INVENTORY, 'seconds');
                        }

                        Log::info("Listing Inbound Shipments (" . ($pagination) . ") -> sortBy [{$this->sortBy}], sortOrder [{$this->sortOrder}], shipmentStatus [{$this->shipmentStatus}], updatedAfter [{$this->updatedAfter}], updatedBefore [{$this->updatedBefore}] maxResults [{$this->maxResults}], nextToken [{$nextToken}]");

                        $updatedAfter = DateHelper::formatDateISO8601($this->updatedAfter);
                        $updatedBefore = DateHelper::formatDateISO8601($this->updatedBefore, true);

                        $updatedAfter = new \DateTime($updatedAfter); // convert only if it’s a string
                        $updatedBefore = new \DateTime($updatedBefore); // convert only if it’s a string

                        $response = $amazonAPIClient->listInboundShipments(
                            $this->sortBy,
                            $this->sortOrder,
                            $this->shipmentStatus,
                            $updatedAfter,
                            $updatedBefore,
                            $this->maxResults,
                            $nextToken
                        );
                        $apiResp = $response->json();

                    } catch (TooManyRequestsException $e) {
                        Log::error($e->getMessage());
                        $apiRetry = true;
                        $sleepTime = calculateIncrementalSleepTime(DELAY_AWD_LIST_INVENTORY, $apiRetryCount);
                        Log::info("Waiting for $sleepTime secs...");
                        sleep($sleepTime);
                    } catch (\Exception $e) {

                        Log::error($e);
                        $apiResp = null;

                        if (stripos($e->getMessage(), "Too Many Requests") !== false || stripos($e->getMessage(), "QuotaExceeded") !== false) {
                            $apiRetry = true;
                            $sleepTime = calculateIncrementalSleepTime(DELAY_AWD_LIST_INVENTORY, $apiRetryCount);
                            Log::info("Waiting for $sleepTime secs...");
                            sleep($sleepTime);
                        } else {
                            $apiRetry = false;
                        }
                    }
                } while ($apiRetryCount < $apiMaxTries && $apiRetry);

            } while ($pollRetry && $pollTryCount <= $pollMaxTries);

            $nextToken = @$apiResp['nextToken'];
            $nextToken = empty($nextToken) ? null : $nextToken;

            $isLastChunk = is_null($nextToken);
            $shipments = @$apiResp['shipments'];
            $pagination++;
            $ndJson = $this->convertArrayIntoNDJSON($shipments, $isLastChunk);

            file_put_contents($json_output_file, $ndJson, FILE_APPEND);

        } while (!is_null($nextToken));

        $fileDownloadedFromAmazonSuccessfully = file_exists($json_output_file);
        if ($fileDownloadedFromAmazonSuccessfully) {
            Log::info("Inventory Data Written on JSON File successfully -> " . $json_output_file);
            $this->uploadToS3($json_output_file);
        }

    }

    public function convertArrayIntoNDJSON($shipments, $isLastChunk)
    {
        return $ndjson = implode(
                "\n",
                array_map(fn($item) => json_encode($item), $shipments)
            ) . ($isLastChunk ? "" : "\n");
    }

    public function uploadToS3($localFilePath)
    {
        $reportDetails = $this->profile_info;
        $reportDetails['reportType'] = AWD_INBOUND_SHIPMENT;
        $reportDetails['startDate'] = $this->updatedAfter;
        $reportDetails['endDate'] = $this->updatedBefore;
        $reportDetails['reportOptions'] = null;
        list($s3filePrefix, $s3fileName) = getReportPrefixAndNameForS3($reportDetails);
        S3Helper::uploadFileToS3($localFilePath, "$s3filePrefix/$s3fileName");
        te_delete_file($localFilePath);
    }
}
