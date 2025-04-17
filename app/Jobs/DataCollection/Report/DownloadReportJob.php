<?php

namespace App\Jobs\DataCollection\Report;

use App\Jobs\Job;
use App\TE\HelperClasses\ETLHelper;
use App\TE\HelperClasses\FileHelper;
use App\TE\HelperClasses\S3Helper;
use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Support\Facades\Log;

class DownloadReportJob extends Job
{
    public $report_info;
    public $retries;
    public $downloadedFileSize;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($report_info, $retries = 0)
    {
        $this->report_info = $report_info;
        $this->retries = $retries;

        if (isVendorCentral($report_info['profile_type'])) {
            $this->onQueue(Q_REPORT_DOWNLOAD_S3);
        } else {
            $this->onQueue(Q_REPORT_DOWNLOAD_S3);
        }

        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {

        $amazonAPIClient = SpApiHelper::getReportsApiClient($this->report_info['profile_id'], $this->report_info['client_authorisation_id'], $this->report_info['marketplaceId']);
        if ($amazonAPIClient === false) {
            $error = 'Download Report Job stopped. Error in refresh token. for profile [' . $this->report_info['profile_id'] . '] Report Type [ ' . $this->report_info['reportType'] . ' ]';
            Log::info($error);
            notifyBugsnagError($error, $this->report_info);
            return;
        }

        $report = null;
        $getReportResp = null;
        $processingStatus = null;

        $getReportRetryCount = 0;
        $getReportMaxTries = 30;

        do {
            try {

                $reportCode = '';
                $reportErrorMessage = "";
                $getReportRetry = false;
                $getReportRetryCount++;

                $report = $amazonAPIClient->getReport($this->report_info['reportId']);
                $getReportResp = $report->json();
                Log::info("Get Report Response");
                Log::info($getReportResp);

//                Log::info("Waiting for {" . DELAY_REPORT_GET . "} sec after getting report...");
//                _sleep(is_local_environment() ? 10 : DELAY_REPORT_GET, 'seconds');

            } catch (\Exception $e) {
                $getReportResp = null;

                Log::error($e);
                $reportCode = $e->getCode();
                $reportErrorMessage = $e->getMessage();

                if (stripos($reportErrorMessage, "You exceeded your quota") !== false) {
                    $reportErrorMessage = "Code:QuotaExceeded -> You exceeded your quota for the requested resource";
                    $reportCode = 429;
                }

                if ($reportCode == 429) {

                    $getReportRetry = true;
                    sleep(calculateIncrementalSleepTime(DELAY_REPORT_GET, $getReportRetryCount));

                } else {

                    $getReportRetry = false;

                }
            }
        } while ($getReportRetryCount < $getReportMaxTries && $getReportRetry);

        if (!is_null($getReportResp)) {

            Log::info($this->report_info);

            Log::info(
                requestReportLog
                (
                    'RequestReportLog',
                    '-',
                    'getReportResponse',
                    $this->queue,
                    $this->report_info['client_id'],
                    $this->report_info['profile_id'],
                    $this->report_info['profile_type'],
                    $this->report_info['startDate'] . '-' . $this->report_info['endDate'],
                    $this->report_info['reportType'],
                    $this->report_info['reportId'],
                    @$reportCode,
                    $reportErrorMessage
                )
            );

        } else {

            $error = "Error in GetReport [{$reportErrorMessage}] - ChannelType= [{$this->report_info['profile_type']}] ChannelId= [" . $this->report_info['profile_id'] . "] - ReportType= [" . $this->report_info['reportType'] . "] - ReportId= [" . $this->report_info['reportId'] . "] - ReTries= [{$this->retries}]";

            Log::info(
                requestReportLog
                (
                    'RequestReportLog',
                    '-',
                    'getReportResponse',
                    $this->queue,
                    $this->report_info['client_id'],
                    $this->report_info['profile_id'],
                    $this->report_info['profile_type'],
                    $this->report_info['startDate'] . '-' . $this->report_info['endDate'],
                    $this->report_info['reportType'],
                    $this->report_info['reportId'],
                    @$reportCode,
                    $reportErrorMessage
                )
            );

            if ($getReportRetry && $reportCode == 429) {
                $reQueued = $this->_reQueueOrExit();
                if (!$reQueued) {
                    Log::error($error);
                    notifyBugsnagError($error, $this->report_info);
                }
            } else {
                Log::error($error);
                notifyBugsnagError($error, $this->report_info);
            }

            return;
        }

        $documentRetryCount = 0;
        $documentMaxRetries = 25;
        $response = [];
        $getReportDocumentResp = null;

        do {
            try {

                $documentCode = '';
                $documentErrorMessage = '';
                $documentRetry = false;
                $documentRetryCount++;

                $reportType = $this->report_info['reportType'];
                if (te_compare_strings($reportType, SALES_AND_TRAFFIC_DAILY_REPORT)) {
                    $reportType = SALES_AND_TRAFFIC_REPORT;
                }

                $response = $amazonAPIClient->getReportDocument($this->report_info['reportDocumentId'], $reportType);
                $getReportDocumentResp = $response->json();
                Log::info("get Report Document response");
                Log::info($getReportDocumentResp);
                $getReportDelay = is_local_environment() ? 15 : DELAY_REPORT_DOCUMENT_GET;
                Log::info("Waiting for {$getReportDelay} sec after getting report document ...");
                _sleep($getReportDelay, 'seconds');

            } catch (\Exception $docExp) {

                $documentCode = $docExp->getCode();
                $documentErrorMessage = $docExp->getMessage();
                if (stripos($documentErrorMessage, "You exceeded your quota") !== false) {
                    $documentErrorMessage = "Code:QuotaExceeded -> You exceeded your quota for the requested resource";
                    $documentCode = 429;
                }

                Log::info(
                    requestReportLog
                    (
                        'RequestReportLog',
                        '-',
                        'getReportDocumentResponse',
                        $this->queue,
                        $this->report_info['client_id'],
                        $this->report_info['profile_id'],
                        $this->report_info['profile_type'],
                        $this->report_info['startDate'] . '-' . $this->report_info['endDate'],
                        $this->report_info['reportType'],
                        $this->report_info['reportId'] . ' - documentId=' . $this->report_info['reportDocumentId'],
                        @$documentCode,
                        $documentErrorMessage
                    )
                );

                if ($documentCode == 429) {
                    $documentRetry = true;
                    sleep(calculateIncrementalSleepTime(DELAY_REPORT_DOCUMENT_GET, $documentRetryCount));
                } else {
                    $documentRetry = false;
                }
            }

        } while ($documentRetry && $documentRetryCount <= $documentMaxRetries);

        Log::info($this->report_info['profile_id'] . " - " . $this->report_info['reportType']);
        Log::info("---------- get report document end --------------");

        if (empty($getReportDocumentResp['url'])) {

            $error = "Error in ReportDocument API Call [" . $documentErrorMessage . "] for Channel " . $this->report_info['profile_id'] . " ReportId: " . $this->report_info['reportId'] . " --  ReportDocumentId: " . $this->report_info['reportDocumentId'];

            if ($documentRetry && $documentCode == 429) {

                $reQueued = $this->_reQueueOrExit();
                if (!$reQueued) {
                    Log::error($error);
                    notifyBugsnagError($error, $this->report_info);
                }

            } else {

                Log::error($error);
                notifyBugsnagError($error, $this->report_info);

            }

        } else {

            Log::info(
                requestReportLog
                (
                    'RequestReportLog',
                    '-',
                    'getReportDocumentResponse',
                    $this->queue,
                    $this->report_info['client_id'],
                    $this->report_info['profile_id'],
                    $this->report_info['profile_type'],
                    $this->report_info['startDate'] . '-' . $this->report_info['endDate'],
                    $this->report_info['reportType'],
                    $this->report_info['reportId'] . ' - documentId=' . $this->report_info['reportDocumentId'],
                    'success',
                    ''
                )
            );

            $fileDownloadURL = @$getReportDocumentResp['url'];

            $compressionAlgo = $this->getCompressionAlgorithm($getReportDocumentResp);

            $this->downloadedFileSize = 0;

            $fileName = make_local_report_file_name($this->report_info);

            $extractedFilePath = public_path('downloads/' . $fileName);
            $extractedFilePath = $extractedFilePath . "." . ETLHelper::getFileExtension($this->report_info['profile_type'], $this->report_info['reportType']);

            $jsonFilePath = $extractedFilePath;
            $downloadedFilePath = $jsonFilePath . '.gz';

            $downloadTries = 0;
            $retryDownload = true;

            do {

                Log::info("Start Downloading File " . $downloadedFilePath . ' tries ' . $downloadTries);
                Log::info("Downloading with Compression Algo [" . $compressionAlgo . "]");

                $downloadFileResponse = FileHelper::downloadZipFileFromAmazonApi($fileDownloadURL, $downloadedFilePath, $compressionAlgo);

                Log::info("Ending Downloading File for file " . $downloadedFilePath . ' tries ' . $downloadTries);

                if ($downloadFileResponse === true) {

                    if (file_exists($downloadedFilePath)) {
                        try {
                            $this->downloadedFileSize = filesize($downloadedFilePath);
                            if ($this->downloadedFileSize > 0) {
                                $retryDownload = false;
                            }
                        } catch (\Exception $e) {
                            $this->downloadedFileSize = 0;
                            Log::info("unable to calculate file size for file " . $downloadedFilePath . " --> " . $e->getMessage());
                        }
                    } else {
                        $this->downloadedFileSize = 0;
                    }

                } else {
                    $this->downloadedFileSize = 0;
                }

                $downloadTries++;

            } while ($downloadTries > 10 && $retryDownload);

            $fileDownloadedFromAmazonSuccessfully = file_exists($downloadedFilePath);
            if ($fileDownloadedFromAmazonSuccessfully) {
                Log::info("File downloaded successfully " . $downloadedFilePath);

                $this->uploadToS3($downloadedFilePath);

            }

        }

    }

    public function uploadToS3($localFilePath)
    {
        list($s3filePrefix, $s3fileName) = getReportPrefixAndNameForS3($this->report_info);

        S3Helper::uploadFileToS3($localFilePath, "$s3filePrefix/$s3fileName");
        te_delete_file($localFilePath);
    }

    public function _reQueueOrExit()
    {
        $reQueued = false;
        if ($this->retries <= 10) {
            $retries = $this->retries + 1;
            $this->report_info['tries'] = $retries;
            $reQueueJob = new DownloadReportJob($this->report_info, $retries);
            $reQueueJob->delay(300);
            dispatch($reQueueJob);
            $reQueued = true;
        }
        return $reQueued;
    }

    public function getCompressionAlgorithm(array $data): ?string
    {
        $algo = $data['compressionAlgorithm']
            ?? $data['compression_algorithm']
            ?? null;
        return empty($algo) ? '' : $algo;
    }

}
