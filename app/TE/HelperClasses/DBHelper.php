<?php

namespace App\TE\HelperClasses;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DBHelper
{

    public static function getTableSchema($table, $connection = 'mysql')
    {
        $describe = DB::connection($connection)->select('SHOW CREATE TABLE ' . $table);
        if (count($describe) > 0) {
            return ltrim(collect($describe[0])['Create Table']) . ";";
        }
        return false;
    }

    public static function retryDatabaseConnection($account_id = '', $connection_name = '')
    {
        $tries = 1;
        $maxTries = 2;
        $sleepTime = 2;
        if (app()->runningInConsole()) {
            $maxTries = 5;
            $sleepTime = 5;
        }

        do {
            Log::info('retryDatabaseConnection() Retry - Connecting to database ' . $tries);
            sleep($sleepTime);
            try {
                $dbConnection = DB::connection($connection_name);
                $dbConnection->reconnect();
                break;
            } catch (\Exception $e) {
                if (!Str::contains($e->getMessage(), database_lost_connection_messages())) {
                    break;
                }
            }
        } while ($tries++ < $maxTries);


    }

    public static function runLoadLocalInfileQuery($query, $account_id = '', $connection_name = '')
    {
        $totalTries = 5;
        $loadingRetry = 0;
        if (app()->runningInConsole()) {
            $totalTries = 10;
        }

        $databaseConnectionRelatedError = false;
        $errorMessage = "";
        do {
            try {

                // if this is lost connection issue then retry connection
                if (Str::contains($errorMessage, database_lost_connection_messages())) {
                    DBHelper::retryDatabaseConnection($account_id);
                }

                //proceed to run the load query
                $dbConnection = DB::connection($connection_name);
                $dbConnection->getPdo()->exec($query);
                $databaseConnectionRelatedError = false;

            } catch (\Exception $e) {

                $errorMessage = $e->getMessage();
                Log::error('runLoadLocalInfileQuery()  Account ( ' . $account_id . ' ) Error for Retries ' . ($loadingRetry + 1) . '  --> ' . $errorMessage);
                $loadQueryError = Str::contains($errorMessage, array_merge(database_lost_connection_messages(), database_lock_timeout_connection_messages()));
                if ($loadQueryError) {
                    if ($loadingRetry == $totalTries) {
                        $errors['query'] = $query;
                        $errors['error'] = $e->getMessage();
                        $errors['loadingRetry'] = $loadingRetry + 1;
                        notifyBugsnagError($e, $errors);
                    } else {
                        usleep(3 * 1000000);
                    }
                    $loadingRetry++;
                    $databaseConnectionRelatedError = true;

                } else {
                    $errors['query'] = $query;
                    $errors['error'] = $e->getMessage();
                    notifyBugsnagError($e, $errors);
                    $databaseConnectionRelatedError = false;
                }
            }

        } while ($databaseConnectionRelatedError && $loadingRetry <= $totalTries);
        return $databaseConnectionRelatedError;
    }
}
