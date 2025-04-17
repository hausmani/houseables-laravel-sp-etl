<?php

use Carbon\Carbon;
use Google\Cloud\BigQuery\BigQueryClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/* marketplace type */
define('AMAZON_MARKETPLACE', 'amazon-selling-partner');

/* channel type */
define('PROFILE_VENDOR_CENTRAL', 'vendor_central');
define('PROFILE_SELLER_CENTRAL', 'seller_central');

/* Seller Reports */
define("SALES_AND_TRAFFIC_REPORT", "GET_SALES_AND_TRAFFIC_REPORT");
define("FBA_INVENTORY_PLANNING_DATA", "GET_FBA_INVENTORY_PLANNING_DATA");

/* Unused Reports */
define("RESERVED_INVENTORY_DATA", "GET_RESERVED_INVENTORY_DATA");
define("FBA_MYI_UNSUPPRESSED_INVENTORY_DATA", "GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA");
define("FBA_MYI_ALL_INVENTORY_DATA", "GET_FBA_MYI_ALL_INVENTORY_DATA");
define("AFN_INVENTORY_DATA", "GET_AFN_INVENTORY_DATA");
define("MERCHANT_LISTINGS_ALL_DATA", "GET_MERCHANT_LISTINGS_ALL_DATA");
define("RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT", "GET_RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT");
define("FBA_SNS_FORECAST_DATA", "GET_FBA_SNS_FORECAST_DATA");
define("FBA_SNS_PERFORMANCE_DATA", "GET_FBA_SNS_PERFORMANCE_DATA");
define("SALES_AND_TRAFFIC_DAILY_REPORT", "GET_SALES_AND_TRAFFIC_DAILY_REPORT");
define("LEDGER_SUMMARY_VIEW_DATA", "GET_LEDGER_SUMMARY_VIEW_DATA");
define("AMAZON_FULFILLED_SHIPMENTS_DATA_GENERAL", "GET_AMAZON_FULFILLED_SHIPMENTS_DATA_GENERAL");
define("FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL", "GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL");
define("FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_GENERAL", "GET_FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_GENERAL");
define("FLAT_FILE_RETURNS_DATA_BY_RETURN_DATE", "GET_FLAT_FILE_RETURNS_DATA_BY_RETURN_DATE");
define("ORDERS_BUYER_INFO", "ORDERS_BUYER_INFO");

define("AWD_INVENTORY", "AWD_INVENTORY");
define("AWD_INBOUND_SHIPMENT", "AWD_INBOUND_SHIPMENT");

define("FBA_INBOUND_SHIPMENT", "FBA_INBOUND_SHIPMENT");
define("FBA_INBOUND_SHIPMENT_ITEM", "FBA_INBOUND_SHIPMENT_ITEM");

define("FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA", "GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA");

define("V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2", "GET_V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2");

/* Vendor Reports */
define("VENDOR_TRAFFIC_REPORT", "GET_VENDOR_TRAFFIC_REPORT");
define("VENDOR_SALES_REPORT", "GET_VENDOR_SALES_REPORT");
define("VENDOR_SALES_SOURCING_REPORT", "GET_VENDOR_SALES_SOURCING_REPORT");
define("VENDOR_SALES_MANUFACTURING_REPORT", "GET_VENDOR_SALES_MANUFACTURING_REPORT");
define("VENDOR_INVENTORY_REPORT", "GET_VENDOR_INVENTORY_REPORT");
define("VENDOR_INVENTORY_SOURCING_REPORT", "GET_VENDOR_INVENTORY_SOURCING_REPORT");
define("VENDOR_INVENTORY_MANUFACTURING_REPORT", "GET_VENDOR_INVENTORY_MANUFACTURING_REPORT");

define('Q_DEFAULT', "sp-default");
define('Q_DISPATCH_JOB', "sp-dispatch-job");
define('Q_REPORT_REQUEST_API', "sp-report-request-api");
define('Q_REPORTS_GET_API', "sp-reports-get-api");
define('Q_SNS_REPORT_GET_API', "sp-sns-report-get-api");
define('Q_REPORT_DOWNLOAD_S3', "sp-report-download-s3");

define('Q_AWD_LIST_INVENTORY', "sp-awd-list-inventory");
define('Q_AWD_LIST_INBOUND_SHIPMENT', "sp-awd-list-inbound-shipment");

define('Q_FBA_INBOUND_SHIPMENT_GET', "sp-fba-inbound-shipment-get");
define('Q_FBA_INBOUND_SHIPMENT_ITEM_GET', "sp-fba-inbound-shipment-item-get");

# unused queues
define('Q_DISPATCH_NEW_DATA_REQUEST', "sp-dispatch-new-data-request");
define('Q_DISPATCH_ORDER_BUYERINFO_REQUEST', "sp-dispatch-order-buyerinfo-request");
define('Q_DISPATCH_ORDER_BUYERINFO_LOAD_BQ', "sp-dispatch-order-buyerinfo-load-bq");
define('Q_SELLER_REPORT_REQUEST_API', "sp-seller-report-request-api");
define('Q_SELLER_REPORT_REQUEST_API_NEW', "sp-seller-report-request-api-new");
define('Q_SELLER_REPORT_REQUEST_API_HISTORICAL', "sp-seller-report-request-api-historical");

define('Q_SELLER_REPORT_GET_API', "sp-seller-report-get-api");
define('Q_SELLER_PREPARE_SQS_MSG', "sp-seller-prepare-sqs-msg");
define('Q_SELLER_REPORT_DOWNLOAD_S3', "sp-seller-report-download-s3");

define('Q_SELLER_ORDER_BUYERINFO_LOAD_BQ', "sp-seller-order-buyerinfo-load-bq");
define('Q_SELLER_ORDER_BUYERINFO_GET_API', "sp-seller-order-buyerinfo-get-api");
define('Q_SELLER_ORDER_BUYERINFO_DISPATCH', "sp-seller-order-buyerinfo-dispatch");

define('Q_VENDOR_REPORT_REQUEST_API_NEW', "sp-report-request-api-new");
define('Q_VENDOR_REPORT_REQUEST_API_HISTORICAL', "sp-report-request-api-historical");

define("DELAY_REPORT_REQUEST", 60);
define("DELAY_REPORT_GET", 1);
define("DELAY_REPORTS_GET", 15);
define("DELAY_AWD_LIST_INVENTORY", 1);
define("DELAY_FBA_INBOUND_SHIPMENT_GET", 1);
define("DELAY_REPORT_DOCUMENT_GET", 60);

if (!function_exists('is_production_environment')) {
    function is_production_environment()
    {
        return app()->environment('production');
    }
}

if (!function_exists('is_staging_environment')) {
    function is_staging_environment()
    {
        return app()->environment('staging');
    }
}

if (!function_exists('is_local_environment')) {
    function is_local_environment()
    {
        return app()->environment('local') || env("APP_ENV") == 'local';
    }
}

function reconnectDatabaseBeforeJobStarted($connectoinName = 'mysql')
{
    try {

        //try reconnecint mysql database again
        \Illuminate\Support\Facades\DB::reconnect($connectoinName);

    } catch (\Exception $exceptionOnReConnect) {
        \Illuminate\Support\Facades\Log::info('Job reconnecting fail');
    }
}

function disconnectDatabaseAferJobProcessed($connectoinName = 'mysql')
{
    try {

        \Illuminate\Support\Facades\DB::connection($connectoinName)->disconnect();
    } catch (\Exception $exceptionOnDisConnect) {
        \Illuminate\Support\Facades\Log::info('Job disconnect fail');
    }
}

function memory_get_usage_mb()
{
    $size = memory_get_usage(false);
    // Convert bytes to megabytes
    return $size / 1024 / 1024;
}

function skipResponseBodyForEntityGetApiCalls($url, $responseJson, $method, $code)
{
    if (
        (stripos($url, 'campaigns/extended') !== false ||
            stripos($url, 'keywords/extended') !== false ||
            stripos($url, 'targets/extended') !== false ||
            stripos($url, 'adGroups/extended') !== false ||
            stripos($url, 'productAds/extended') !== false) && stripos($url, 'IdFilter') === false && strtolower($method) == 'get' && in_array($code, [200])
    ) {
        $responseJson = 'data fetched successfully';
    }
    return $responseJson;
}

if (!function_exists('logAmazonApiRequest')) {
    function logAmazonApiRequest($thisObj, $requestId, $responseRequestId, $response_info, $responseJson, $success, $requestData, $headers, $method, $profileId)
    {
        //if this variable set then disable amazon request log
        if (env('DISABLE_AMAZON_REQUEST_LOG', 'false') == 'true') {
            return;
        }

        try {
            $url = @$response_info['url'];
            if ($url != 'https://api.amazon.com/auth/o2/token') {

                if (is_production_environment()) {
                    $responseJson = skipResponseBodyForEntityGetApiCalls($url, @$responseJson, $method, @$response_info['http_code']);
                }

                $fileDownloadAPICall = stripos($url, '/download') !== false && isset($response_info['redirect_url']);

                if ($fileDownloadAPICall) {
                    $responseJson = $response_info['redirect_url'];
                }

                $requestIdLogObj = new \App\RequestIdLog();
                $requestIdLogObj->requestId = @$requestId;
                $requestIdLogObj->url = @$response_info['url'];
                $requestIdLogObj->response_requestId = @$responseRequestId;
                $requestIdLogObj->request_body = @$requestData;
                $requestIdLogObj->response_body = @$responseJson;
                $requestIdLogObj->code = @$response_info['http_code'];
                $requestIdLogObj->success_status = @$success;
                $requestIdLogObj->profileId = @$profileId;
                $requestIdLogObj->account_id = @$thisObj->account_id;
                $requestIdLogObj->requested_by = @$thisObj->requested_by;
                $requestIdLogObj->rule_id = 0;

                $headers = @$headers ? $headers : [];
                $headers_json = is_array($headers) ? json_encode($headers) : @$headers;

                $requestIdLogObj->headers = $headers_json;
                $requestIdLogObj->method = @$method;

                // if this is a file download API Call then don't save it for now. save it in cache and if required will be stored later on.
                // we have only 30 seconds to download the file so we need to be do it ASAP
                if ($fileDownloadAPICall) {
                    global $queryCacher;
                    $requestIdLogObj->created_at = Carbon::now()->toDateTimeString();
                    if (!isset($queryCacher['requestIdLogObj'])) {
                        $queryCacher['requestIdLogObj'] = [];
                    }
                    $queryCacher['requestIdLogObj'][] = $requestIdLogObj;
                } else {
                    $requestIdLogObj->save();
                }

            }
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
        }
    }
}

if (!function_exists('notifyBugsnagError')) {
    function notifyBugsnagError($exception, $errorDetails = [], $type = 'error', $cacheKey = '')
    {

        if (!($exception instanceof \Exception)) {
            $exception = new \Exception($exception);
        }

        if (env('NOTIFY_BUGSNAG_THROW_EXCEPTION', false)) {
            throw $exception;
        }

        Log::error($exception);
        return;

        $env = $app_env = env('APP_ENV', 'local');
        $errorDetails['Environment'] = $env;
        $type = $type == 'info' ? 'info' : 'error';

        //if cacheKey is provided that means we need to push the notification to bugsnag only once
        if (app()->runningInConsole()) {
            //we will only cache while app is running in console
            if (!empty($cacheKey)) {
                if (\App\TE\HelperClasses\CacheHelper::isCacheSet($cacheKey)) {
                    return;
                } else {
                    //default time is 60 min but we change it
                    \App\TE\HelperClasses\CacheHelper::setCache($cacheKey, '1');
                }
            }
        }

        \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyException($exception, function ($report) use ($errorDetails, $type) {
            $report->setSeverity($type);
            $report->setMetaData([
                'AMS Error Detail' => $errorDetails
            ]);
            $stacktrace = $report->getStacktrace();
            $frames = $stacktrace->getFrames();
            if (@($frames[0]['method']) == 'notifyBugsnagError') {
                $stacktrace->removeFrame(0);
            }
            if (@($frames[0]['method']) == 'App\TE\Database\MySqlConnection::__callParentMethod') {
                $stacktrace->removeFrame(0);
                $methods = ['statement', 'delete', 'insert', 'select', 'selectOne', 'update'];
                foreach ($methods as $method) {
                    if (@($frames[1]['method']) == 'App\TE\Database\MySqlConnection::' . $method) {
                        $stacktrace->removeFrame(1);
                    }
                }
            }
        });

    }
}

function getProfileTypeCode($profileType): string
{
    $profileTypeCode = '';
    if (isSellerCentral($profileType)) {
        $profileTypeCode = PROFILE_SELLER_CENTRAL;
    } else if (isVendorCentral($profileType)) {
        $profileTypeCode = PROFILE_VENDOR_CENTRAL;
    }

    return $profileTypeCode;
}

function getProfileType($profileType): string
{
    return getProfileTypeCode($profileType);
}

function isVendorCentral($profileType): bool
{
    $profileType = empty($profileType) ? "" : $profileType;
    return iCheckInArray($profileType, ['vendor central', 'vendor_central', 'vc', 'vendor-central', 'vendorcentral'
            , 'vendor']) !== -1;
}

function isSellerCentral($profileType): bool
{
    $profileType = empty($profileType) ? "" : $profileType;
    return iCheckInArray($profileType, ['seller central', 'seller_central', 'sc', 'seller-central', 'sellercentral',
            'seller']) !== -1;
}

function fetchSessionTokenForSPIAPI()
{
    $resp = \App\TE\HelperClasses\S3Helper::generateAWSSessionToken();
    $resp = $resp->get("Credentials");
    return $resp;
}

function storeDataInBigQuery($projectId, $datasetId, $tableId, $jsonData)
{
    // Initialize BigQuery client
    $bigQuery = new BigQueryClient([
        'projectId' => $projectId,
    ]);

    // Get the dataset and table
    $dataset = $bigQuery->dataset($datasetId);
    $table = $dataset->table($tableId);

    // Decode JSON data
    $data = json_decode($jsonData, true);

    // Ensure data is in an array format expected by BigQuery
    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid JSON data');
    }

    // Insert data into the table
    $insertResponse = $table->insertRows([
        ['data' => $data]
    ]);

    // Check for errors
    if ($insertResponse->isSuccessful()) {
        echo "Data inserted successfully.";
    } else {
        foreach ($insertResponse->failedRows() as $row) {
            echo "Failed to insert row: " . json_encode($row['row']) . " with errors: " . json_encode($row['errors']);
        }
    }
}


function removeDuplicates($orderIds)
{
    $uniqueOrders = [];
    $seenIds = [];

    foreach ($orderIds as $order) {
        $orderId = $order['amazon_order_id'];
        if (!in_array($orderId, $seenIds)) {
            $uniqueOrders[] = $order;
            $seenIds[] = $orderId;
        }
    }

    return $uniqueOrders;
}

function _duration($value, $duration = 'second')
{
    $x = 1;
    if (iCheckInArray($duration, ['m', 'min', 'mins', 'minute', 'minutes']) !== -1) {
        $x = 60;
    } else if (iCheckInArray($duration, ['h', 'hr', 'hrs', 'hour', 'hours']) !== -1) {
        $x = 60 * 60;
    } else if (iCheckInArray($duration, ['s', 'sec', 'secs', 'second', 'seconds']) !== -1) {
        $x = 1;
    } else if (iCheckInArray($duration, ['d', 'dy', 'day', 'days']) !== -1) {
        $x = 60 * 60 * 24;
    }
    return $value * $x;
}

function _sleep($value, $duration = 'second')
{
    sleep(_duration($value, $duration));
}

function dispatchGetSellerReportJob()
{
    $json = '{
                "profile_id": 2,
                "client_id": 2,
                "profile_type": "seller_central",
                "client_authorisation_id": 2,
                "marketplaceId": "ATVPDKIKX0DER",
                "countryCode": "US",
                "profileId": null,
                "sellerId": "A1PVHR5Y85DH15",
                "inactive_reports": [
                    "GET_AMAZON_FULFILLED_SHIPMENTS_DATA_GENERAL",
                    "GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL",
                    "GET_LEDGER_SUMMARY_VIEW_DATA"
                ],
                "retry_attempts": 1,
                "reportType": "GET_SALES_AND_TRAFFIC_DAILY_REPORT",
                "startDate": "2023-11-06",
                "endDate": "2024-11-05",
                "requested_at": "2024-11-06 19:07:56",
                "payload": {
                    "reportOptions": {
                        "asinGranularity": "PARENT",
                        "dateGranularity": "DAY"
                    },
                    "reportType": "GET_SALES_AND_TRAFFIC_REPORT",
                    "dataStartTime": "2023-11-06T00:00:00Z",
                    "dataEndTime": "2024-11-05T23:59:59Z",
                    "marketplaceIds": [
                        "ATVPDKIKX0DER"
                    ]
                },
                "reportOptions": {
                    "asinGranularity": "PARENT",
                    "dateGranularity": "DAY"
                },
                "reportId": "1253700020034",
                "reportDocumentId": "",
                "processingStatus": "",
                "tries": 0
            }';
    $job = new \App\Jobs\DataCollection\Report\GetSellerReportJob(json_decode($json, true));
    $job->onConnection('sync');
    dispatch($job);

}

function te_delete_file($filePath)
{
    $delete = env("DELETE_DOWNLOADED_FILES", "yes");
    if (iCheckInArray($delete, ['no', 'false', false, 0, '0']) != -1) {
        $delete = false;
    } else {
        $delete = true;
    }
    $delete = !is_local_environment() ? true : $delete;

    if ($delete) {
        @unlink($filePath);
    }
}
