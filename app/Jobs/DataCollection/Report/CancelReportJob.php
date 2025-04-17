<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\Job;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;

class CancelReportJob extends Job
{
    public $profile_info;
    public $reports;
    public $processingStatuses;
    public $createdSince;
    public $createdUntil;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info, $reports, $processingStatuses, $createdSince, $createdUntil)
    {
        $this->profile_info = $profile_info;
        $this->reports = !is_array($reports) ? explode(",", $reports) : $reports;
        $this->processingStatuses = !is_array($processingStatuses) ? explode(",", strtoupper($processingStatuses)) : $processingStatuses;
        $this->createdSince = date("Y-m-d", strtotime($createdSince)) . 'T00:00:00Z';
        $this->createdUntil = date("Y-m-d", strtotime($createdUntil)) . 'T23:59:59Z';

        $this->onQueue(Q_SELLER_REPORT_GET_API);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {

        $nextToken = null;
        do {

            $amazonAPIClient = SpApiHelper::getReportsApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'],$this->profile_info['marketplaceId']);
            if ($amazonAPIClient === false) {
                Log::info('Cancel Report Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
                return;
            }

            $per_page = 10;
            Log::info("Getting $per_page [" . implode(",", $this->processingStatuses) . "] Reports...");
            list($reports, $nextToken) = $this->_getReports($amazonAPIClient, $per_page, $nextToken);
            Log::info(count($reports) . " [" . implode(",", $this->processingStatuses) . "] Reports Found.");

            foreach ($reports as $index => $report) {
                $reportId = $report->getReportId();
                $this->_cancelReport($amazonAPIClient, $index, $reportId);
            }

        } while (count($reports) >= $per_page);

    }

    private function _getReports($amazonAPIClient, $per_page, $nextToken = null)
    {
        $getReportMaxTries = 10;
        $getReportRetryCount = 0;
        $reports = null;

        do {

            $getReportRetry = false;
            $getReportRetryCount++;

            try {

//                if (empty($nextToken)) {
                $reportsResp = $amazonAPIClient->getReports(
                    $this->reports,
                    $this->processingStatuses,
                    [$this->profile_info['marketplaceId']],
                    $per_page,
                    $this->createdSince,
                    $this->createdUntil,
                    $nextToken
                );
//                } else {
//                    $reportsResp = $amazonAPIClient->getReports(
//                        null,
//                        null,
//                        null,
//                        null,
//                        null,
//                        null,
//                        $nextToken
//                    );
//                }

                $reports = $reportsResp->getReports();
//                $nextToken = $reportsResp->getNextToken();

            } catch (\Exception $e) {
                $reports = null;

                Log::error($e);

                if ($e->getCode() == 429) {
                    $getReportRetry = true;
                    sleep(calculateIncrementalSleepTime(DELAY_REPORT_GET, $getReportRetryCount));
                } else {
                    $getReportRetry = false;
                }
            }
        } while ($getReportRetryCount < $getReportMaxTries && $getReportRetry);

        if (is_null($reports)) {
            $reports = [];
        }

        return [$reports, $nextToken];
    }

    private function _cancelReport($amazonAPIClient, $index, $reportId)
    {
        $cancelReportMaxTries = 10;
        $cancelReportRetryCount = 0;
        $reportCancelled = false;
        $delay = 15;

        do {

            $cancelReportRetry = false;
            $cancelReportRetryCount++;

            try {

                Log::info("cancelling report [$index] -> $reportId");
                $amazonAPIClient->cancelReport($reportId);
                sleep($delay);
                $reportCancelled = true;

            } catch (\Exception $e) {
                $reportCancelled = false;

                if (stripos($e->getMessage(), 'exceeded your quota') !== false) {
                    $cancelReportRetry = true;

                    Log::info("Quota is exceeded.... Sleeping for " . ($delay * 2) . " seconds");
                    sleep($delay * 2);
                } else {
                    $cancelReportRetry = false;
                    Log::info("Failed to cancel report [$index] -> $reportId (" . $e->getMessage() . ')');
                }

            }
        } while ($cancelReportRetryCount < $cancelReportMaxTries && $cancelReportRetry);

        return $reportCancelled;
    }
}
