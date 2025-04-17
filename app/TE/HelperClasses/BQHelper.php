<?php

namespace App\TE\HelperClasses;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Support\Facades\Log;

class BQHelper
{

    public static function getClient()
    {
        return new BigQueryClient([
            'projectId' => env('GOOGLE_PROJECT_ID', 'amazon-sp-report-loader'),
            'keyFilePath' => 'bq-credentials-sp.json',
        ]);
    }

    public static function checkIfRetryErrorMessage($errorMessage)
    {
        $errors = [
            'ConnectionResetError',
            'Exceeded rate limits',
            'too many table update operations for this table',
            'Retrying may solve the problem',
            'Too many DML statements',
            'Quota exceeded',
            'table exceeded quota for imports or query appends per table',
            'table exceeded quota for Number of partition modifications',
            'Resources exceeded',
            'concurrent update',
            'An internal error occurred'
        ];
        foreach ($errors as $error) {
            if (stripos($errorMessage, $error) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function runQuery($query, $isSelectQuery = false, $maxTries = 10)
    {
        $query_status = false;
        $queryResult = [];
        $bigQuery = self::getClient();
        $retryCount = 0;
        do {
            $retry = false;

            try {
                $retryCount++;

                Log::info($query);

                $jobConfig = $bigQuery->query($query);
                $queryJob = $bigQuery->runQuery($jobConfig);

                if ($queryJob->isComplete()) {
                    $query_status = true;
                    Log::info("Merge operation completed successfully");

                    if ($isSelectQuery) {
                        foreach ($queryJob as $row) {
                            $queryResult[] = $row;
                        }
                    }

                } else {
                    Log::error("Merge operation failed.");
                    foreach ($queryJob->errors() as $error) {
                        Log::error($error);
                        print_r($error);
                    }
                }

            } catch (\Exception $exp) {
                if (self::checkIfRetryErrorMessage($exp->getMessage())) {
                    $retry = true;
                    Log::error("Error in BQ Query -> [{$exp->getMessage()}]... TRYING AGAIN ({$retryCount})....");
                } else {
                    Log::error($exp);
                }
            }
        } while ($retry && $retryCount <= $maxTries);

        return [$query_status, $queryResult];
    }

    public static function loadParquetFilesInTable($tableId, $datasetId, $schema, $gcsUris, $maxTries = 10)
    {
        $dataLoaded = false;

        $retryCount = 0;
        do {
            $retry = false;
            try {
                $bigQuery = self::getClient();
                $dataset = $bigQuery->dataset($datasetId);
                $tempTable = $dataset->table($tableId);

                $loadConfig = $tempTable
                    ->loadFromStorage($gcsUris)
                    ->schema($schema)
                    ->sourceFormat('PARQUET')
                    ->ignoreUnknownValues(true);

                $job = $tempTable->runJob($loadConfig);

                // poll the job until it is complete
                $backoff = new ExponentialBackoff(10);
                $backoff->execute(function () use ($job) {
                    Log::info('Waiting for job to complete' . PHP_EOL);
                    $job->reload();
                    if (!$job->isComplete()) {
                        Log::warning('Job is not complete yet.' . PHP_EOL);
                    }
                });

                if (isset($job->info()['status']['errorResult'])) {
                    $error = $job->info()['status']['errorResult']['message'];
                    Log::error('Error running job:');
                    Log::error($error);
                } else {
                    $dataLoaded = true;
                    Log::info('Data imported successfully' . PHP_EOL);
                }
            } catch (\Exception $exp) {
                if (self::checkIfRetryErrorMessage($exp->getMessage())) {
                    $retry = true;
                    Log::error("Error in Load Parquet in Temp Table -> [{$exp->getMessage()}]... TRYING AGAIN ({$retryCount})....");
                }
            }
        } while ($retry && $retryCount <= $maxTries);

        return $dataLoaded;
    }

    public static function createTable($tableName, $datasetId, $schema, $maxTries = 10)
    {
        $created = false;
        $bigQuery = self::getClient();

        $retryCount = 0;
        do {
            $retry = false;
            try {
                $dataset = $bigQuery->dataset($datasetId);
                $table = $dataset->createTable($tableName, [
                    'schema' => $schema,
                    'expirationTime' => (time() + 900) * 1000
                ]);
                $created = true;
            } catch (\Exception $exp) {
                if (self::checkIfRetryErrorMessage($exp->getMessage())) {
                    $retry = true;
                    Log::error("Error in Creating Temp Table -> [{$exp->getMessage()}]... TRYING AGAIN ({$retryCount})....");
                }
            }
        } while ($retry && $retryCount <= $maxTries);
        return $created;
    }

}
