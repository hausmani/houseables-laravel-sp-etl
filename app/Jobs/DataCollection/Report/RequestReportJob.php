<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\DataCollection\Core\RequestFileFromAPI;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\MyRedis;
use App\TE\HelperClasses\MyReportRequestPayload;
use App\TE\HelperClasses\S3Helper;
use Carbon\Carbon;

use Illuminate\Support\Facades\Log;
use SellingPartnerApi\Seller\ReportsV20210630\Api;

class RequestReportJob extends RequestFileFromAPI
{

    public $maxTries = 5;
    public $setRedisCacheForInactiveAccounts = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info, $reportTypes, $backfill, $customDateRange, $reportRange, $sleepInJob = true, $max_retry_attempts = 10)
    {
        parent::__construct($profile_info, $reportTypes, $backfill, $customDateRange, $reportRange, $sleepInJob, $max_retry_attempts);

        $queueName = Q_REPORT_REQUEST_API;
//        if (te_compare_strings($backfill, 'new')) {
//            $queueName = Q_VENDOR_REPORT_REQUEST_API_NEW;
//        } else if (te_compare_strings($backfill, 'historical') || te_compare_strings($backfill, 'smart')) {
//            $queueName = Q_VENDOR_REPORT_REQUEST_API_HISTORICAL;
//        }
        $this->onQueue($queueName);

        $this->switchToTestQueueIfTestServer();
    }

    protected function _getReportTypes()
    {
        return getReportTypesToDownload($this->profile_info['profile_type'], $this->reportTypes);
    }

    protected function _requestFileFromAPI(Api $APIClient, $reportTypes)
    {
        foreach ($reportTypes as $reportType) {

            if ($this->_checkIfReportIsInactive($reportType)) {
                Log::info("Skipping INACTIVE Report [{$reportType}] for profile [{$this->profile_info['profile_id']}]");
                continue;
            }

            $dateRanges = ETLHelper::getApiPatterns($this->profile_info['profile_type'], $reportType, $this->backfill, $this->customDateRange, $this->reportRange);

            if (te_compare_strings($this->backfill, 'smart') || te_compare_strings($this->backfill, 'historical')) {
                $dateRangesOnS3 = getReportDateRangeFoldersOnS3($this->profile_info, $reportType);
            } else {
                $dateRangesOnS3 = [];
            }

            foreach ($dateRanges as $reportDatesRange) {

                $reportStartDate = $reportDatesRange[0];
                $reportEndDate = $reportDatesRange[1];

                $startDateStr = date("Ymd", strtotime($reportStartDate));
                $endDateStr = date("Ymd", strtotime($reportEndDate));
                $dateRangeStr = "{$startDateStr}-{$endDateStr}";

                if (count($dateRangesOnS3) > 0) {
                    if (in_array($dateRangeStr, $dateRangesOnS3)) {
                        Log::info("{$reportType} for [{$dateRangeStr}] is already on S3 for profile [{$this->profile_info['profile_id']}]");
                        continue;
                    }
                }

                $reportRequestPayload = new MyReportRequestPayload(
                    $this->profile_info['profile_type'],
                    $this->profile_info['marketplaceId'],
                    $reportType,
                    $reportStartDate,
                    $reportEndDate
                );

                $reportRequestCount = 1;
                $maximumTries = $this->maxTries;
                $retryCodes = ['QuotaExceeded', 429, 500, 501, 502, 503, 504];

                do {

                    $retryReportRequest = false;
                    $reportRequestCount++;

                    $report_info = $this->profile_info;
                    $report_info['reportType'] = $reportType;
                    $report_info['startDate'] = $reportStartDate;
                    $report_info['endDate'] = $reportEndDate;
                    $report_info['requested_at'] = Carbon::now()->toDateTimeString();
                    $report_info['payload'] = $reportRequestPayload->getPayload();;
                    $report_info['reportOptions'] = $reportRequestPayload->getReportOptions();
                    $report_info['reportId'] = '';
                    $report_info['reportDocumentId'] = '';
                    $report_info['processingStatus'] = '';
                    $report_info['tries'] = 0;

                    list($returnedCode, $errorMsg) = $this->_requestReport(
                        $APIClient,
                        $report_info
                    );

                    if (in_array($returnedCode, $retryCodes)) {

                        $retryReportRequest = true;

                        $sleepTime = calculateIncrementalSleepTime(DELAY_REPORT_REQUEST, $reportRequestCount, 30);

                        Log::info(
                            requestReportLog
                            (
                                'RequestReportLog',
                                $this->job->getJobId(),
                                'Retry-After',
                                $this->queue,
                                $this->profile_info['client_id'],
                                $this->profile_info['profile_id'],
                                $this->profile_info['profile_type'],
                                $reportStartDate . ' - ' . $reportEndDate,
                                $reportType,
                                '-',
                                $returnedCode,
                                $errorMsg,
                                $sleepTime
                            ));

                        sleep($sleepTime);

                    }

                } while ($retryReportRequest && $reportRequestCount <= $maximumTries);

                if (in_array($returnedCode, $retryCodes)) {

                    Log::warning(
                        requestReportLog
                        (
                            'RequestReportLog',
                            $this->job->getJobId(),
                            're-queued',
                            $this->queue,
                            $this->profile_info['client_id'],
                            $this->profile_info['profile_id'],
                            $this->profile_info['profile_type'],
                            $reportStartDate . ' - ' . $reportEndDate,
                            $reportType,
                            '-',
                            $returnedCode,
                            @$errorMsg
                        )
                    );

                    $retryDateRange = date("Ymd", strtotime($reportStartDate)) . ',' . date("Ymd", strtotime($reportEndDate));
                    $retry_attempts = (empty($this->profile_info['retry_attempts']) ? 1 : $this->profile_info['retry_attempts']) + 1;
                    if ($retry_attempts <= $this->max_retry_attempts) {
                        Log::info("Request Job ReQueued ({$retry_attempts}) for profile {$this->profile_info['profile_id']} - Report [{$reportType}] - DateRange [{$retryDateRange}]");
                        $retryReportRequestJob = new RequestReportJob($this->profile_info, [$reportType], 'custom', $retryDateRange, $this->reportRange);
                        $retryReportRequestJob->onQueue($this->queue);
                        $retryReportRequestJob->delay(_duration(15, 'minutes'));
                        dispatch($retryReportRequestJob);
                    } else {
                        $errorMsg = "Request Job STOPPED after ({$retry_attempts}) attempts for profile {$this->profile_info['profile_id']} - Report [{$reportType}] - DateRange [{$retryDateRange}]";
                        Log::warning($errorMsg);
                        notifyBugsnagError($errorMsg, [
                            'account id' => $this->profile_info['client_id'],
                            'profile id' => $this->profile_info['profile_id'],
                            'profile type' => $this->profile_info['profile_type'],
                            'date range' => $retryDateRange,
                            'report type' => $reportType,
                            'error code' => $returnedCode,
                        ]);
                    }

                } else if (te_compare_strings('ACCESS_REVOKED', $returnedCode) || in_array($returnedCode, [401, 403, 421])) {

                    Log::warning(
                        requestReportLog
                        (
                            'RequestReportLog',
                            $this->job->getJobId(),
                            'Stopped',
                            $this->queue,
                            $this->profile_info['client_id'],
                            $this->profile_info['profile_id'],
                            $this->profile_info['profile_type'],
                            $reportStartDate . ' - ' . $reportEndDate,
                            $reportType,
                            '-',
                            $returnedCode,
                            @$errorMsg
                        )
                    );

                    notifyBugsnagError($returnedCode . ' - ' . $errorMsg, [
                        'account id' => $this->profile_info['client_id'],
                        'profile id' => $this->profile_info['profile_id'],
                        'profile type' => $this->profile_info['profile_type'],
                        'date range' => $reportStartDate . ' - ' . $reportEndDate,
                        'report type' => $reportType,
                        'error code' => $returnedCode,
                    ]);

                    if ($this->setRedisCacheForInactiveAccounts) {
                        $redis_data = [
                            'ClientId' => $this->profile_info['client_id'],
                            'ProfileId' => $this->profile_info['profile_id'],
                            'ErrorCode' => $returnedCode,
                        ];
                        MyRedis::redis_insert_set_value('inactive_profiles', json_encode($redis_data));
                    }

                    return;
                } else {

                    if (!te_compare_strings($returnedCode, 'success')) {
                        Log::info(
                            requestReportLog
                            (
                                'RequestReportLog',
                                $this->job->getJobId(),
                                'requestReportResponse',
                                $this->queue,
                                $this->profile_info['client_id'],
                                $this->profile_info['profile_id'],
                                $this->profile_info['profile_type'],
                                $reportStartDate . ' - ' . $reportEndDate,
                                $reportType,
                                '-',
                                $returnedCode,
                                $errorMsg
                            )
                        );
                    }
                }

                if ($this->sleepInJob) {
                    $delayInReportRequest = is_local_environment() ? 15 : DELAY_REPORT_REQUEST;
                    Log::info("Waiting for {$delayInReportRequest} sec...");
                    _sleep($delayInReportRequest, 'seconds');
                }
            }
        }
    }

    protected
    function _requestReport(Api $APIClient, $report_info)
    {
        $payload = $report_info['payload'];
        $reportType = $report_info['reportType'];
        $startDate = @$report_info['startDate'];
        $endDate = @$report_info['endDate'];

        $returnedCode = "";
        $errorMsg = '';

        try {

            Log::info(json_encode($payload));
            $reportResponse = $APIClient->createReport($payload);
            $jsonResp = $reportResponse->json();
            Log::info($jsonResp);
            $reportId = @$jsonResp['reportId'];

            if (!empty($reportId)) {

                $report_info['reportId'] = $reportId;

                if (isSellerCentral($this->profile_info['profile_type'])) {

                    $redisKey = getRedisKeyForSPAPIReports($reportType, $reportId);
                    $value = MyRedis::set_value($redisKey, json_encode($report_info));
                    if ($value === false) {
                        $errorMsg = "Redis value not set for ReportId=[{$reportId}] for Profile=[{$this->profile_info['profile_id']} ReportType=[{$reportType}]";
                        Log::error($errorMsg);
                        return [-1, $errorMsg];
                    }
                    Log::info("Redis Value Set -> [ $redisKey =  for profile -> " . $report_info['profile_id'] . " ]");

//                if (is_local_environment()) {
//                    Log::info("Getting Seller Report on local in a job ....");
//                    $prepareSQSMessageJob = new GetReportJob($report_info, true);
//                    $prepareSQSMessageJob->onConnection('sync');
//                    dispatch($prepareSQSMessageJob);
//                }
                }

                $returnedCode = "Success";

                Log::info(
                    requestReportLog
                    (
                        'RequestReportLog',
                        $this->job->getJobId(),
                        'requestReportResponse',
                        $this->queue,
                        $this->profile_info['client_id'],
                        $this->profile_info['profile_id'],
                        $this->profile_info['profile_type'],
                        $startDate . ' - ' . $endDate,
                        $reportType,
                        $reportId,
                        $returnedCode,
                        $returnedCode
                    )
                );

                if (isVendorCentral($this->profile_info['profile_type'])) {
                    Log::info("Get Report Document Job Dispatched for Channel[{$this->profile_info['profile_id']}] ReportType[{$reportType}] ReportId[{$reportId}] DateRange[{$startDate}-{$endDate}]");
                    $job = new GetReportJob($report_info);
                    $delay = is_local_environment() ? 30 : 900;
                    $job->delay($delay);
                    dispatch($job);
                }
            } else {
                throw new \Exception("ReportId is null.", 400);
            }

        } catch (\Exception $e) {

            $returnedCode = $e->getCode();
            $errorMsg = $e->getMessage();

            if (stripos($errorMsg, "You exceeded your quota") !== false) {
                $errorMsg = "Code:QuotaExceeded -> You exceeded your quota for the requested resource";
                $returnedCode = 429;
            } else if (stripos($errorMsg, "The request has an invalid grant parameter") !== false) {
                $errorMsg = "The request has an invalid grant parameter : refresh_token. User may have revoked or didn't grant";
                $returnedCode = "ACCESS_REVOKED";
            } else if (stripos($errorMsg, "Access to requested resource is denied") !== false) {
                $errorMsg = "Unauthorized: Access to requested resource is denied.";
                $returnedCode = 403;
            } else if (stripos($errorMsg, "Access to the resource is forbidden") !== false) {
                $errorMsg = "forbidden: Access to the resource is forbidden.";
                $returnedCode = 403;
            }

        }

        return [$returnedCode, $errorMsg];
    }
}
