<?php

namespace App\Jobs\DataCollection\Core;

use App\Jobs\Job;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SellingPartnerApi\Seller\ReportsV20210630\Api;

abstract class RequestFileFromAPI extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    // DB Column i-e $profile_id = $client_profile_info->id
    public $profile_info;
    public $reportTypes;
    public $backfill;
    public $customDateRange;
    public $reportRange;
    //if we wanted to sleep in the job after we request a report or get an error
    public $sleepInJob;
    public $max_retry_attempts;
    public $maxTries;

    abstract protected function _getReportTypes();

    protected function _checkIfReportIsInactive($reportType)
    {
        $inactiveReport = false;
        if (!empty($this->profile_info['inactive_reports'])) {
            if (iCheckInArray($reportType, $this->profile_info['inactive_reports']) !== -1) {
                $inactiveReport = true;
            }
        }
        return $inactiveReport;
    }

    abstract protected function _requestFileFromAPI(Api $APIClient, $reportTypes);

    abstract protected function _requestReport(Api $APIClient, $report_info);


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info, $reportTypes, $backfill, $customDateRange, $reportRange, $sleepInJob, $max_retry_attempts = 10)
    {
        $this->profile_info = $profile_info;
        $this->reportTypes = $reportTypes;
        $this->backfill = $backfill;
        $this->customDateRange = $customDateRange;
        $this->reportRange = $reportRange;
        $this->sleepInJob = $sleepInJob;
        $this->max_retry_attempts = empty($max_retry_attempts) ? 10 : $max_retry_attempts;
    }

    private function initializeVariables()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Running for the Profile type -> " . $this->profile_info['profile_type'] . " profile_id -> " . $this->profile_info['profile_id']);

        $amazonAPIClient = SpApiHelper::getReportsApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'], $this->profile_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('Request ProfileReport Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
            changeOAuthStatus($this->profile_info['client_authorisation_id'], $this->profile_info['profile_id'], 0);
            return;
        }

        $this->initializeVariables();
        //check if this is the first time we are going to download report for this profile then download 2 months of data
        $reportTypes = $this->_getReportTypes();
        foreach ($reportTypes as $reportType) {
            $this->_requestFileFromAPI($amazonAPIClient, [$reportType]);
        }
    }
}
