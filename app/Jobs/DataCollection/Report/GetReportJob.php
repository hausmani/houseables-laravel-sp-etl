<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\Job;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;

class GetReportJob extends Job
{
    public $report_info;
    public $localTestingForSeller;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($report_info, $localTestingForSeller = false)
    {
        $this->report_info = $report_info;
        $this->localTestingForSeller = $localTestingForSeller;

        $this->onQueue(Q_REPORT_GET_API);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {
        $amazonAPIClient = SpApiHelper::getReportsApiClient($this->report_info['profile_id'], $this->report_info['client_authorisation_id'], $this->report_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('Get Report Job stopped here due to error in refresh token. for profile [' . $this->report_info['profile_id'] . ']');
            return;
        }

        $report = null;
        $getReportResp = null;
        $reportDocument = null;
        $processingStatus = null;

        $pollTryCount = 0;
        $pollMaxTries = 7;

        do {

            $pollTryCount++;
            $pollRetry = false;

            $getReportMaxTries = 10;
            $getReportRetryCount = 0;

            do {

                $getReportRetry = false;
                $getReportRetryCount++;

                try {

                    $report = $amazonAPIClient->getReport($this->report_info['reportId']);
                    $getReportResp = $report->json();
                    Log::info("Get Report Response");
                    Log::info($getReportResp);

                } catch (\Exception $e) {

                    $getReportResp = null;

                    Log::error($e);

                    if ($e->getCode() == 429) {
                        $getReportRetry = true;
                        sleep(calculateIncrementalSleepTime(DELAY_REPORT_GET, $getReportRetryCount));
                    } else {
                        $getReportRetry = false;
                    }
                }
            } while ($getReportRetryCount < $getReportMaxTries && $getReportRetry);

            if (is_null($getReportResp)) {
                if ($getReportRetry) {
                    $this->_reQueueOrExit();
                }
                return;
            }

            $this->report_info['processingStatus'] = @$getReportResp['processingStatus'];

            if (iCheckInArray($this->report_info['processingStatus'], ['IN_PROGRESS', 'IN_QUEUE']) !== -1) {

                $pollRetry = true;
                $pollingWait = is_local_environment() ? 5 : 30;
                $pollingWait = calculateIncrementalSleepTime($pollingWait, $pollTryCount);
                Log::info("Get Report for Profile " . $this->report_info['profile_id'] . " ReportType= [{$this->report_info['reportType']}] DateRange= [{$this->report_info['startDate']} - {$this->report_info['endDate']}] ReportId= [{$this->report_info['reportId']}] Status= [{$this->report_info['processingStatus']}]-> Polling again... $pollingWait sec");
                sleep($pollingWait);

            } else if (iCheckInArray($this->report_info['processingStatus'], ['DONE']) !== -1) {

                Log::info("Get Report for Profile " . $this->report_info['profile_id'] . " ReportType= [{$this->report_info['reportType']}] DateRange= [{$this->report_info['startDate']} - {$this->report_info['endDate']}] ReportId= [{$this->report_info['reportId']}] Status= [{$this->report_info['processingStatus']}]");

                $this->report_info['reportDocumentId'] = @$getReportResp['reportDocumentId'];

                if ($this->localTestingForSeller) {

                    sendReportStatusNotificationOnSQS($this->report_info);

                } else {

                    $downloadReportJob = new DownloadReportJob($this->report_info);
                    dispatch($downloadReportJob);

                }

            } else {

                $pollRetry = false;
                Log::error("Report for Profile " . $this->report_info['profile_id'] . " ReportType= [{$this->report_info['reportType']}] ReportId= [{$this->report_info['reportId']}] Status= [{$this->report_info['processingStatus']}]-> Stopping here...");
                Log::info($report);

            }

        } while ($pollRetry && $pollTryCount <= $pollMaxTries);

    }

    private function _reQueueOrExit()
    {
        $tries = $this->report_info['tries'];
        if ($tries <= 15) {
            $this->report_info['tries'] = $this->report_info['tries'] + 1;
            Log::info("Get Report Job ReQueued Again.");
            $job = new GetReportJob($this->report_info, $this->localTestingForSeller);
            $job->delay(300);
            dispatch($job);
        }
    }
}
