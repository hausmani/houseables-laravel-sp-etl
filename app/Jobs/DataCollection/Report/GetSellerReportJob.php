<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\DataCollection\DispatchBuyerOrderInfoLoadBQJob;
use App\Jobs\DataCollection\DispatchBuyerOrderInfoRequestJob;
use App\Jobs\DataCollection\DispatchJobsJob;
use App\Jobs\DataCollection\DispatchNewDataRequestJob;
use App\Jobs\DataCollection\DispatchReportRequestJob;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\MyRedis;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Contracts\Queue\Job as LaravelJob;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetSellerReportJob
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(Q_SELLER_REPORT_GET_API);
        $this->onConnection("sqs-json");
    }

    private function reportTypeIsValid($reportType)
    {
        return iCheckInArray($reportType, getReportTypesToDownload(PROFILE_SELLER_CENTRAL)) != -1;
    }

    private function reportTypeIsAutoCreated($reportType)
    {
        return iCheckInArray($reportType, ETLHelper::getAutoCreatedReportTypesToDownload(PROFILE_SELLER_CENTRAL)) != -1;
    }

    private function downloadReportsRequestedFromOutsite($reportType)
    {
        return iCheckInArray($reportType, ETLHelper::getOutsiteRequestedReportsToDownload()) != -1;
    }

    private function reportCanBeDownloaded($reportNotificationData)
    {
        $sellerId = @$reportNotificationData['sellerId'];
        $reportType = @$reportNotificationData['reportType'];
        $reportId = @$reportNotificationData['reportId'];
        $processingStatus = @$reportNotificationData['processingStatus'];

        $error = '';

        if ($this->reportTypeIsAutoCreated($reportType)) {
            if (!te_compare_strings($processingStatus, 'DONE')) {
                $error = "SNS --> [{$reportType}] ({$processingStatus}) _ ReportId[{$reportId}] - AUTO_CREATED";
            }
            return [
                empty($error),
                $reportType,
                $reportId,
                $processingStatus
            ];
        }

        if ($this->reportTypeIsValid($reportType)) {

            $redisValue = $this->checkReportInRedis($reportType, $reportId);

            if ($redisValue === false) {
                $error = "SNS -> [{$reportType}] ({$processingStatus}) _ ReportId[{$reportId}] - OUTSIDE";
                if (te_compare_strings($processingStatus, 'DONE')) {
                    if ($this->downloadReportsRequestedFromOutsite($reportType)) {

                        return [
                            true,
                            $reportType,
                            $reportId,
                            $processingStatus
                        ];
                    }
                }

            } else {
                $redisArray = json_decode($redisValue, true);
                $profile_id = @$redisArray['profile_id'];
                if (!te_compare_strings($processingStatus, 'DONE')) {
                    $error = "SNS -> [{$reportType}] ({$processingStatus}) _ ReportId[{$reportId}]";
                    Log::error($error);
                    notifyBugsnagError($error, $reportNotificationData);
                    $this->deleteRedisKey($reportType, $reportId);
                }
            }

        } else {
            $error = "SNS -> [{$reportType}] ({$processingStatus}) _ ReportId[{$reportId}] - INVALID";
        }

        if (!empty($error)) {
            Log::warning($error);
        }

        return [empty($error), $reportType, $reportId, $processingStatus];
    }

    private function deleteRedisKey($reportType, $reportId)
    {
        if (!$this->reportTypeIsAutoCreated($reportType)) {
            MyRedis::delete_key(getRedisKeyForSPAPIReports($reportType, $reportId));
        }
    }

    private function checkReportInRedis($reportType, $reportId)
    {
        $redisKey = getRedisKeyForSPAPIReports($reportType, $reportId);
        list($errorInRedis, $redisValue) = MyRedis::get_value($redisKey);
        $error = '';
        if ($redisValue === false) {
            if ($errorInRedis) {
                $error = "Redis value not get for ReportId=[$reportId] for ReportType=[$reportType]";
                Log::error($error);
            } else {
                $error = "Redis value not found for RedisKey = [$redisKey]";
            }
        }

        // if sales and traffic report type, but no redis value, it means the report is daily report
        // so, here we are going to check the daily report in redis
        if (te_compare_strings($reportType, SALES_AND_TRAFFIC_REPORT)) {
            if ($redisValue === false) {
                Log::info("Checking Report " . SALES_AND_TRAFFIC_DAILY_REPORT . " in Redis for reportId: {$reportId}");
                return $this->checkReportInRedis(SALES_AND_TRAFFIC_DAILY_REPORT, $reportId);
            }
        }

        return $redisValue;
    }

    private function setReportInfoFromNotification($notification, &$report_info)
    {
        $report_info['profile_type'] = PROFILE_SELLER_CENTRAL;
//        $report_info['reportType'] = @$notification['reportType'];
        $report_info['reportId'] = @$notification['reportId'];
        $report_info['reportDocumentId'] = @$notification['reportDocumentId'];
        $report_info['processingStatus'] = @$notification['processingStatus'];
        $report_info['sellerId'] = @$notification['sellerId'];
    }

    private function setReportInfoFromProfile($profile, &$report_info)
    {
        $report_info['profile_type'] = $profile->profile_type;
        $report_info['profile_id'] = @$profile->profile_id;
        $report_info['marketplaceId'] = @$profile->marketplaceId;
        $report_info['client_id'] = @$profile->client_id;
        $report_info['client_authorisation_id'] = @$profile->client_authorisation_id;
        $report_info['profileId'] = @$profile->profileId;
        $report_info['sellerId'] = @$profile->sellerId;
    }

    private function setReportInfoFromRedis($redisValue, &$report_info)
    {
        $redisArray = json_decode($redisValue, true);
        foreach ($redisArray as $key => $value) {
            $report_info[$key] = $value;
        }
    }

    private function dispatchDownloadReportJob($report_info)
    {
        $downloadReportJob = new DownloadReportJob($report_info);
        dispatch($downloadReportJob);
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function handle(LaravelJob $job, ?array $data)
    {
        /*
         * we are handling the dispatch report request here because all message on sqs-json queue are coming here
         */
        if (Str::contains($job->getQueue(), Q_DISPATCH_JOB, true)) {
            Log::info("Dispatch Job Notification Received.");
            Log::info($data);
            $job = new DispatchJobsJob();
            $job->handle($data);
            return;
        }

        $report_info = [];
        $reportNotificationData = null;

        $payload = $data['payload'] ?? null;
        if (!is_null($payload)) {
            $reportNotificationData = $payload['reportProcessingFinishedNotification'] ?? null;
        }

        if (!is_null($reportNotificationData)) {

            list($canBeDownloaded, $reportType, $reportId, $processingStatus) = $this->reportCanBeDownloaded($reportNotificationData);

            if (!$canBeDownloaded) {
                return;
            }

            Log::info($reportNotificationData, ["<<<<<<<<<<<<<<< SELLER Report Notification"]);

            // we will check redis only when report is NOT among the reports created automatically by Amazon e-g. SETTLEMENT reports
            if ($this->reportTypeIsAutoCreated($reportType) || $this->downloadReportsRequestedFromOutsite($reportType)) {

                // if report is created automatically by Amazon, we will have to get the report and populate report_info from that
                $this->setReportInfoFromNotification($reportNotificationData, $report_info);
                $report_info['reportType'] = $reportType;
                $profiles = getProfilesBySellerId(PROFILE_SELLER_CENTRAL, $report_info["sellerId"]);
                $thisReport = null;
                foreach ($profiles as $profile) {
                    $thisReport = $this->getReportByReportId($profile, $reportId);
                    if (!is_null($thisReport)) {
                        break;
                    }
                }

                if (!is_null($thisReport)) {
                    Log::info($thisReport);
                    $report_info['startDate'] = date('Y-m-d', strtotime(@$thisReport['dataStartTime']));
                    $report_info['endDate'] = date('Y-m-d', strtotime(@$thisReport['dataEndTime']));
                    $report_info['reportOptions'] = [];
                    $reportMarketplaceIds = @$thisReport['marketplaceIds'];
                    foreach ($profiles as $profile) {
                        if (iCheckInArray($profile->marketplaceId, $reportMarketplaceIds) != -1) {
                            $this->setReportInfoFromProfile($profile, $report_info);
                            $this->dispatchDownloadReportJob($report_info);
                        }
                    }
                }

            } else {

                $redisValue = $this->checkReportInRedis($reportType, $reportId);
                if ($redisValue !== false) {
                    Log::info("Getting Report Info From Redis - {$reportType} - {$reportId}");
                    $this->setReportInfoFromRedis($redisValue, $report_info);
                    Log::info("Getting Report Info From Notifications - {$reportType} - {$reportId}");
                    $this->setReportInfoFromNotification($reportNotificationData, $report_info);
                    Log::info("Dispatching Download Report Job - {$reportType} - {$reportId}", $report_info);
                    $this->dispatchDownloadReportJob($report_info);
                }

                $this->deleteRedisKey($reportType, $reportId);

            }

        } else {
            $error = "Error in Get Seller Report Job -> reportProcessingFinishedNotification NOT FOUND";
            notifyBugsnagError($error, [
                'report details' => $data
            ]);
            Log::info($error);
        }
    }

    private function getReportByReportId($profile, $reportId)
    {

        $amazonApiClient = SpApiHelper::getReportsApiClient($profile->id, $profile->client_authorisation_id, $profile->marketplaceId);

        $getReportMaxTries = 10;
        $getReportRetryCount = 0;
        $report = null;
        $getReportResp = null;

        do {

            $getReportRetry = false;
            $getReportRetryCount++;

            try {

                $report = $amazonApiClient->getReport($reportId);
                $getReportResp = $report->json();
                Log::info("Get Report Response");
                Log::info($getReportResp);

            } catch (\Exception $e) {
                $getReportResp = null;

                if ($e->getCode() == 429) {
                    $getReportRetry = true;
                    sleep(calculateIncrementalSleepTime(DELAY_REPORT_GET, $getReportRetryCount));
                } else {
                    $getReportRetry = false;
                }
            }
        } while ($getReportRetryCount < $getReportMaxTries && $getReportRetry);

        return $getReportResp;
    }
}
