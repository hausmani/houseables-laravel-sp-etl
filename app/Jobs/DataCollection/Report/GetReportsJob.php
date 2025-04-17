<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\Job;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\SpApiHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

class GetReportsJob extends Job
{
    public $profile_info;
    public $reportTypes;
    public $processStatuses;
    public $createdSince;
    public $createdUntil;
    public $localTestingForSeller;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($profile_info, $reportTypes, $processStatuses, $createdSince, $createdUntil)
    {
        $this->profile_info = $profile_info;
        $this->reportTypes = is_array($reportTypes) ? $reportTypes : explode(",", $reportTypes);
        $this->processStatuses = empty($processStatuses) ? ['DONE'] : (is_array($processStatuses) ? $processStatuses : explode(",", $processStatuses));
        $this->createdSince = empty($createdSince) ? null : new \DateTime(DateHelper::formatDateISO8601($createdSince));
        $this->createdUntil = empty($createdUntil) ? null : new \DateTime(DateHelper::formatDateISO8601($createdUntil, true));

        $this->onQueue(Q_REPORTS_GET_API);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {
        $amazonAPIClient = SpApiHelper::getReportsApiClient($this->profile_info['profile_id'], $this->profile_info['client_authorisation_id'], $this->profile_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            Log::info('Get Reports Job stopped here due to error in refresh token. for profile [' . $this->profile_info['profile_id'] . ']');
            return;
        }

        $report = null;
        $getReportsResp = null;
        $reportDocument = null;
        $processingStatus = null;

        $pollTryCount = 0;
        $pollMaxTries = 7;


        $pollTryCount++;
        $pollRetry = false;

        $getReportsMaxTries = 10;
        $getReportsRetryCount = 0;

        $nextToken = null;

        $filteredReports = [];

        $apiCalls = 1;

        do {
            do {

                $getReportsRetry = false;
                $getReportsRetryCount++;

                try {

                    Log::info("Getting Reports from API (" . ($apiCalls) . ")", [
                            "reportTypes" => $this->reportTypes,
                            "processStatuses" => $this->processStatuses,
                            "createdSince" => $this->createdSince,
                            "createdUntil" => $this->createdUntil,
                        ]
                    );

                    $_reportTypes = $this->reportTypes;
                    $_processStatuses = $this->processStatuses;
                    $_marketplaceId = [$this->profile_info['marketplaceId']];
                    $_pageSize = 100;
                    $_createdSince = $this->createdSince;
                    $_createdUntil = $this->createdUntil;

                    if (!is_null($nextToken)) {
                        $_reportTypes = $_processStatuses = $_marketplaceId = $_pageSize = $_createdSince = $_createdUntil = null;
                    }
                    $apiResp = $amazonAPIClient->getReports(
                        $_reportTypes,
                        $_processStatuses,
                        $_marketplaceId,
                        $_pageSize,
                        $_createdSince,
                        $_createdUntil,
                        $nextToken
                    );
                    $getReportsResp = $apiResp->json();

                    Log::info("Waiting for {" . DELAY_REPORTS_GET . "} sec after getting reports....");
                    _sleep(DELAY_REPORTS_GET, 'seconds');
                    $apiCalls++;

                } catch (TooManyRequestsException $tmre) {

                    Log::error($tmre->getMessage());
                    $getReportsResp = null;
                    $getReportsRetry = true;
                    sleep(calculateIncrementalSleepTime(DELAY_REPORTS_GET, $getReportsRetryCount));

                } catch (\Exception $e) {

                    $getReportsResp = null;
                    Log::error($e->getMessage());
                    if (stripos($e->getMessage(), "Too Many Requests") !== false || stripos($e->getMessage(), "QuotaExceeded") !== false) {

                        $getReportsRetry = true;
                        sleep(calculateIncrementalSleepTime(DELAY_REPORTS_GET, $getReportsRetryCount));

                    } else {
                        $getReportsRetry = false;
                    }
                }
            } while ($getReportsRetryCount < $getReportsMaxTries && $getReportsRetry);

            if (is_null($getReportsResp) || empty($getReportsResp['reports'])) {

                $nextToken = null;

            } else {

                $nextToken = @$getReportsResp['nextToken'];
                $nextToken = empty($nextToken) ? null : $nextToken;

                $reports = $getReportsResp['reports'];
                $filteredReports = $this->filterReports($reports, $filteredReports);

            }

        } while (!is_null($nextToken));

        foreach ($filteredReports as $filteredReport) {

            $report_info = $this->profile_info;
            $report_info['reportType'] = $filteredReport['reportType'];
            $report_info['startDate'] = $filteredReport['dataStartTime']->format(\DateTime::ATOM);
            $report_info['endDate'] = $filteredReport['dataEndTime']->format(\DateTime::ATOM);
            $report_info['requested_at'] = $filteredReport['processingStartTime'];
            $report_info['payload'] = $filteredReport;
            $report_info['reportOptions'] = '';
            $report_info['reportId'] = $filteredReport['reportId'];
            $report_info['reportDocumentId'] = $filteredReport['reportDocumentId'];
            $report_info['processingStatus'] = $filteredReport['processingStatus'];
            $report_info['tries'] = 0;

            if (iCheckInArray($filteredReport['processingStatus'], ['IN_PROGRESS', 'IN_QUEUE']) !== -1) {

                Log::info("Get Report Document Job Dispatched for Channel[{$this->profile_info['profile_id']}] ReportType[{$filteredReport['reportType']}] ReportId[{$filteredReport['reportId']}] DateRange[{$report_info['startDate']}-{$report_info['endDate']}]");
                $job = new GetReportJob($report_info);
                $delay = is_local_environment() ? 30 : 900;
                $job->delay($delay);
                dispatch($job);

            } else if (iCheckInArray($filteredReport['processingStatus'], ['DONE']) !== -1) {

                Log::info("Download Report for Profile " . $this->profile_info['profile_id'] . " ReportType= [{$filteredReport['reportType']}] DateRange= [{$report_info['startDate']} - {$report_info['endDate']}] ReportId= [{$filteredReport['reportId']}] Status= [{$filteredReport['processingStatus']}]");

                $downloadReportJob = new DownloadReportJob($report_info);
                dispatch($downloadReportJob);

            } else {

                Log::error("Download Report for Profile " . $this->profile_info['profile_id'] . " ReportType= [{$filteredReport['reportType']}] DateRange= [{$report_info['startDate']} - {$report_info['endDate']}] ReportId= [{$filteredReport['reportId']}] Status= [{$filteredReport['processingStatus']}] -> stopped here...");
                Log::info($filteredReport);

            }
        }
    }

    private function filterReports($reports, $previousFilteredReports)
    {
        $reportRanges = [];

        foreach ($reports as $report) {
            $reportId = $report['reportId'];

            $start = new \DateTime(date("Y-m-d", strtotime($report['dataStartTime'])));
            $end = new \DateTime(date("Y-m-d", strtotime($report['dataEndTime'])));

            $reportRanges[$reportId] = [
                'dataStartTime' => $start,
                'dataEndTime' => $end,
                'reportType' => $report['reportType'],
                'processingStatus' => $report['processingStatus'],
                'reportId' => $report['reportId'],
                'reportDocumentId' => $report['reportDocumentId'],
                'processingStartTime' => $report['processingStartTime'],
            ];
        }

        $reportRanges = array_merge($previousFilteredReports, $reportRanges);

        $filtered = [];

        foreach ($reportRanges as $id1 => $range1) {
            $isCovered = false;

            foreach ($reportRanges as $id2 => $range2) {
                if ($id1 === $id2) continue;

                // Check if range1 is fully inside range2
                if ($range1['dataStartTime'] >= $range2['dataStartTime'] && $range1['dataEndTime'] <= $range2['dataEndTime']) {
                    $isCovered = true;
                    break;
                }
            }

            if (!$isCovered) {
                $filtered[$id1] = [
                    'dataStartTime' => $range1['dataStartTime'],
                    'dataEndTime' => $range1['dataEndTime'],
                    'reportType' => $range1['reportType'],
                    'processingStatus' => $range1['processingStatus'],
                    'reportId' => $range1['reportId'],
                    'reportDocumentId' => $range1['reportDocumentId'],
                    'processingStartTime' => $range1['processingStartTime'],
                ];
            }
        }

        Log::info($filtered);

        uasort($filtered, function ($a, $b) {
            return strtotime($a['processingStartTime']) <=> strtotime($b['processingStartTime']);
        });


        return $filtered;
    }
}
