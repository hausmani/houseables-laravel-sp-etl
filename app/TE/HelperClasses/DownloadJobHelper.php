<?php

use App\TE\HelperClasses\S3Helper;
use App\Models\ClientProfile;
use App\TE\HelperClasses\ETLHelper;
use App\TE\QuerySQS;
use Aws\Lambda\LambdaClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


function calculateSleepTimeInDownloadJob($apiCount)
{
    $sleepTime = ceil((pow(2, ($apiCount + 2)) * 100) / 1000);
    $sleepTime = (int)$sleepTime;
    return $sleepTime;
}

function calculateIncrementalSleepTime($retryAfter, $apiCount, $delay = 1)
{
    $sleepTime = calculateSleepTimeInDownloadJob($apiCount);
    $sleepTime = $sleepTime * $delay;
    $sleepTime += $retryAfter;
    $sleepTime = $sleepTime > 900 ? 900 : $sleepTime;
    return $sleepTime;
}

function make_local_report_file_name($report_info)
{
    $reportId = str_replace('amzn1.clicksAPI.v1.p1.', '', $report_info['reportId']);
    $fileName = getProfileType($report_info['profile_type']) . '_' . $report_info['client_id'] . '_' . $report_info['profile_id'] . '_' . $report_info['reportType'] . '_' . $report_info['startDate'] . '_' . $report_info['endDate'] . '_' . $reportId . '_' . time();
    return $fileName;
}

function parseProfileTypeArg($profileTypes = ''): array
{
    $allProfileTypes = [PROFILE_VENDOR_CENTRAL, PROFILE_SELLER_CENTRAL];
    if (is_array($profileTypes)) {
        if (empty($profileTypes)) {
            return $allProfileTypes;
        }
    } else {
        if (te_compare_strings("", $profileTypes)) {
            return $allProfileTypes;
        }
        $profileTypes = explode(",", $profileTypes);
    }
    $profileTypesList = [];
    foreach ($profileTypes as $profileType) {
        $profileType = trim($profileType);
        $profileType = getProfileType($profileType);
        if (!empty($profileType)) {
            $profileTypesList[] = $profileType;
        }
    }
    return $profileTypesList;
}


function getProfilesForDataDownload($profileType, $p = '', $checkOAuth = true, $cid = ''): array
{
    $profilesForDataDownload = [];
    $profiles = ClientProfile::select("client_profile_info.*");
    $profiles = $profiles->where('client_profile_info.active', true);

    if (!empty($cid)) {
        $client_ids = explode(',', $cid);
        $profiles = $profiles->whereIn('client_profile_info.client_id', $client_ids);
    }

    if (!empty($profileType)) {
        $profiles = $profiles->where('profile_type', $profileType);
    } else {
        $profiles = $profiles->whereIn('profile_type', [PROFILE_VENDOR_CENTRAL, PROFILE_SELLER_CENTRAL]);
    }

    if (!empty($p) && !te_compare_strings($p, 'all')) {
        $p = explode(',', $p);
        $profiles = $profiles->whereIn('client_profile_info.id', $p);
    } else {
        if ($checkOAuth) {
            $profiles = $profiles->join('client_authorisations', 'client_authorisations.id', '=', 'client_profile_info.client_authorisation_id');
            $profiles = $profiles->where("client_authorisations.active", true);
        }
    }
    $profiles = $profiles->get();

    foreach ($profiles as $profile) {
        $profilesForDataDownload[$profile->id] = $profile;
    }
    return $profilesForDataDownload;
}

function getClientsForDataDownload($c, $profile_type, $profile_id = '')
{
    $profile_type_clause = empty($profile_type) ? " AND profile_type IN ('" . PROFILE_SELLER_CENTRAL . "','" . PROFILE_VENDOR_CENTRAL . "')" : " AND profile_type='{$profile_type}'";
    $client_id_clause = empty($c) ? "" : " AND client_id IN({$c})";
    $profile_id_clause = empty($profile_id) ? "" : " AND id IN({$profile_id})";
    $query = "SELECT client_id FROM client_profile_info p
                  INNER JOIN client_info c
                  ON c.id=p.client_id
                  WHERE 1=1 {$profile_type_clause} {$profile_id_clause} {$client_id_clause} GROUP BY client_id";
    $client_ids = collect(DB::select($query))->toArray();

    return $client_ids;
}

function getProfilesBySellerId($profileType, $sellerId, $marketplaceId = '', $checkOAuth = true): array
{
    $profiles = [];
    $profilesData = ClientProfile::select("client_profile_info.*");
    $profilesData = $profilesData->where('client_profile_info.active', true)
        ->where('profile_type', $profileType)
        ->where('sellerId', $sellerId);

    if (!empty($marketplaceId)) {
        $profilesData = $profilesData->where('marketplaceId', $marketplaceId);
    }

    if ($checkOAuth) {
        $profilesData = $profilesData->join('client_authorisations', 'client_authorisations.id', '=', 'client_profile_info.client_authorisation_id');
        $profilesData = $profilesData->where("client_authorisations.active", true);
    }
    $profilesData = $profilesData->get();

    foreach ($profilesData as $profile) {
        $profiles[$profile->id] = $profile;
    }
    return $profiles;
}

function requestReportLog($logPrefix, $jobId, $event, $queue, $account_id, $channel_id, $channelType, $dateRange, $reportType, $reportId, $code = '', $error = '', $retryAfterTime = '')
{
    $data = [
        'LogPrefix' => $logPrefix,
        'JobId' => $jobId,
        'Event' => $event,
        'Time' => date("Y-m-d H:i:s"),
        'Queue' => $queue,
        'account_id' => $account_id,
        'channel_id' => $channel_id,
        'ChannelType' => getProfileType($channelType),
        'DateRange' => $dateRange,
        'ReportType' => $reportType,
        'ReportId' => $reportId,
        'Code' => $code,
        'Error' => $error,
        'RetryAfter' => $retryAfterTime
    ];
    return ', "' . implode('" , "', $data) . '"';
}

function getKeyValueStringsFromArray($array, $separator = '_')
{
    $keyValues = [];
    foreach ($array as $key => $value) {
        $keyValues[] = "{$key}={$value}";
    }
    return implode($separator, $keyValues);
}

function getMarketplaceInfo($getInfoBy, $value, $info = 'region')
{
    $marketplaceInfo = [
        'A2EUQ1WTGCTBG2' => [
            'marketplaceId' => 'A2EUQ1WTGCTBG2',
            'country' => 'Canada',
            'countryCode' => 'CA',
            'sales_channel' => 'Amazon.ca',
            'region' => 'NA'
        ],
        'ATVPDKIKX0DER' => [
            'marketplaceId' => 'ATVPDKIKX0DER',
            'country' => 'United States',
            'countryCode' => 'US',
            'sales_channel' => 'Amazon.com',
            'region' => 'NA',
        ],
        'A1AM78C64UM0Y8' => [
            'marketplaceId' => 'A1AM78C64UM0Y8',
            'country' => 'Mexico',
            'countryCode' => 'MX',
            'sales_channel' => 'Amazon.com.mx',
            'region' => 'NA',
        ],
        'A2Q3Y263D00KWC' => [
            'marketplaceId' => 'A2Q3Y263D00KWC',
            'country' => 'Brazil',
            'countryCode' => 'BR',
            'sales_channel' => 'Amazon.com.br',
            'region' => 'NA',
        ],
        'A1RKKUPIHCS9HS' => [
            'marketplaceId' => 'A1RKKUPIHCS9HS',
            'country' => 'Spain',
            'countryCode' => 'ES',
            'sales_channel' => 'Amazon.es',
            'region' => 'EU',
        ],
        'A1F83G8C2ARO7P' => [
            'marketplaceId' => 'A1F83G8C2ARO7P',
            'country' => 'United Kingdom',
            'countryCode' => 'UK',
            'sales_channel' => 'Amazon.co.uk',
            'region' => 'EU',
        ],
        'A13V1IB3VIYZZH' => [
            'marketplaceId' => 'A13V1IB3VIYZZH',
            'country' => 'France',
            'countryCode' => 'FR',
            'sales_channel' => 'Amazon.fr',
            'region' => 'EU',
        ],
        'AMEN7PMS3EDWL' => [
            'marketplaceId' => 'AMEN7PMS3EDWL',
            'country' => 'Belgium',
            'countryCode' => 'BE',
            'sales_channel' => 'Amazon.com.be',
            'region' => 'EU',
        ],
        'A1805IZSGTT6HS' => [
            'marketplaceId' => 'A1805IZSGTT6HS',
            'country' => 'Netherlands',
            'countryCode' => 'NL',
            'sales_channel' => 'Amazon.nl',
            'region' => 'EU',
        ],
        'A1PA6795UKMFR9' => [
            'marketplaceId' => 'A1PA6795UKMFR9',
            'country' => 'Germany',
            'countryCode' => 'DE',
            'sales_channel' => 'Amazon.de',
            'region' => 'EU',
        ],
        'APJ6JRA9NG5V4' => [
            'marketplaceId' => 'APJ6JRA9NG5V4',
            'country' => 'Italy',
            'countryCode' => 'IT',
            'sales_channel' => 'Amazon.it',
            'region' => 'EU',
        ],
        'A2NODRKZP88ZB9' => [
            'marketplaceId' => 'A2NODRKZP88ZB9',
            'country' => 'Sweden',
            'countryCode' => 'SE',
            'sales_channel' => 'Amazon.se',
            'region' => 'EU',
        ],
        'AE08WJ6YKNBMC' => [
            'marketplaceId' => 'AE08WJ6YKNBMC',
            'country' => 'South Africa',
            'countryCode' => 'ZA',
            'sales_channel' => 'Amazon.co.za',
            'region' => 'EU',
        ],
        'A1C3SOZRARQ6R3' => [
            'marketplaceId' => 'A1C3SOZRARQ6R3',
            'country' => 'Poland',
            'countryCode' => 'PL',
            'sales_channel' => 'Amazon.pl',
            'region' => 'EU',
        ],
        'ARBP9OOSHTCHU' => [
            'marketplaceId' => 'ARBP9OOSHTCHU',
            'country' => 'Egypt',
            'countryCode' => 'EG',
            'sales_channel' => 'Amazon.eg',
            'region' => 'EU',
        ],
        'A33AVAJ2PDY3EV' => [
            'marketplaceId' => 'A33AVAJ2PDY3EV',
            'country' => 'Turkey',
            'countryCode' => 'TR',
            'sales_channel' => 'Amazon.com.tr',
            'region' => 'EU',
        ],
        'A17E79C6D8DWNP' => [
            'marketplaceId' => 'A17E79C6D8DWNP',
            'country' => 'Saudi Arabia',
            'countryCode' => 'SA',
            'sales_channel' => 'Amazon.sa',
            'region' => 'EU',
        ],
        'A2VIGQ35RCS4UG' => [
            'marketplaceId' => 'A2VIGQ35RCS4UG',
            'country' => 'United Arab Emirates (U.A.E.)',
            'countryCode' => 'AE',
            'sales_channel' => 'Amazon.ae',
            'region' => 'EU',
        ],
        'A21TJRUUN4KGV' => [
            'marketplaceId' => 'A21TJRUUN4KGV',
            'country' => 'India',
            'countryCode' => 'IN',
            'sales_channel' => 'Amazon.in',
            'region' => 'EU',
        ],
        'A19VAU5U5O7RUS' => [
            'marketplaceId' => 'A19VAU5U5O7RUS',
            'country' => 'Singapore',
            'countryCode' => 'SG',
            'sales_channel' => 'Amazon.sg',
            'region' => 'FE',
        ],
        'A39IBJ37TRP1C6' => [
            'marketplaceId' => 'A39IBJ37TRP1C6',
            'country' => 'Australia',
            'countryCode' => 'AU',
            'sales_channel' => 'Amazon.com.au',
            'region' => 'FE',
        ],
        'A1VC38T7YXB528' => [
            'marketplaceId' => 'A1VC38T7YXB528',
            'country' => 'Japan',
            'countryCode' => 'JP',
            'sales_channel' => 'Amazon.co.jp',
            'region' => 'FE',
        ],
    ];

    $data = [];
    foreach ($marketplaceInfo as $marketplaceId => $marketplaceData) {
        if (isset($marketplaceData[$getInfoBy])) {
            $data[$marketplaceData[$getInfoBy]] = $marketplaceData[$info];
        }
    }

    if (!empty($value)) {
        $data = @$data[$value];
    }

    return $data;
}

function sendReportStatusNotificationOnSQS($reportDetails)
{
    $sellerId = $reportDetails['sellerId'];
    $reportType = $reportDetails['reportType'];
    $reportId = $reportDetails['reportId'];
    $reportDocumentId = $reportDetails['reportDocumentId'];

    $notificationData = [
        "payload" => [
            "reportProcessingFinishedNotification" => [
                "sellerId" => $sellerId,
                "accountId" => "amzn1.merchant.o.A1MTDCIU7240KJ",
                "reportId" => $reportId,
                "reportType" => $reportType,
                "processingStatus" => "DONE",
                "reportDocumentId" => $reportDocumentId
            ]
        ]
    ];

    Log::info("Sending Message on SNS Queue [" . Q_SNS_REPORT_GET_API . "] for reportId = {$reportId}");
    $queueName = Q_SNS_REPORT_GET_API;
    $queueName = is_local_environment() ? 'local-' . $queueName : $queueName;
    QuerySQS::sendMessage($notificationData, $queueName);
}

function getRedisKeyForSPAPIReports($reportType, $reportId)
{
    return strtolower("spetl_" . $reportType . "_" . $reportId);
}


function changeOAuthStatus($oAuthId, $channel_id, $status = 0)
{
    Log::info("Disabling oAuth[{$oAuthId}] for Channel= [{$channel_id}]");
    DB::table('client_authorisations')
        ->where('id', $oAuthId)
        ->update(array('active' => $status));
}

function getS3FolderPathForReportDates($profile_info, $reportType)
{
    $profile_info['reportType'] = $reportType;
    $payloadConfig = ETLHelper::allReportsConfiguration($profile_info['profile_type'], $reportType, 'payload');
    $profile_info['reportOptions'] = $payloadConfig['reportOptions'];
    return getReportPrefixAndNameForS3($profile_info, true);
}

function getReportDateRangeFoldersOnS3($profile_info, $reportType)
{
    $s3_prefix = getS3FolderPathForReportDates($profile_info, $reportType);
    $s3_paths = S3Helper::getFilesList($s3_prefix);
    $dateRanges = [];
    foreach ($s3_paths as $s3_path) {
        $pathParts = explode('/', $s3_path);
        $dateRanges[] = end($pathParts);  // Get the last part which is the directory name
    }
    return $dateRanges;
}

function getReportPrefixAndNameForS3($reportDetails, $getPath = false)
{
    $reportType = $reportDetails['reportType'];
    $marketplaceId = $reportDetails['marketplaceId'];
    $client_id = $reportDetails['client_id'];
    $sellerId = $reportDetails['sellerId'];
    $profile_type = $reportDetails['profile_type'];
    $reportOptions = $reportDetails['reportOptions'];

    $reportOptionsInPrefix = '';
    $reportOptionsInName = '';

    if (!empty($reportOptions)) {
        $reportOptionsInPrefix = "__" . implode("_", array_values($reportOptions));
        $reportOptionsInName = "_" . getKeyValueStringsFromArray($reportOptions);
    }

    $mainFolder = "amazon-selling-partners-api";
    $reportTypeFolder = $reportType;
    $prefixParts = [
        $mainFolder,
        $reportTypeFolder . $reportOptionsInPrefix,
        getMarketplaceInfo('marketplaceId', $marketplaceId, 'countryCode'),
        $client_id,
        $sellerId
    ];

    if ($getPath) {
        return implode("/", $prefixParts);
    }

    $startDate = date("Ymd", strtotime($reportDetails['startDate']));
    $endDate = date("Ymd", strtotime($reportDetails['endDate']));

    $prefixParts[] = "{$startDate}-{$endDate}";

    $prefix = implode("/", $prefixParts);

    $ext = ETLHelper::getFileExtension($profile_type, $reportType) . '.gz';
    if (iCheckInArray($reportType, [AWD_INBOUND_SHIPMENT, AWD_INVENTORY, FBA_INBOUND_SHIPMENT, FBA_INBOUND_SHIPMENT_ITEM]) != -1) {
        $ext = "json";
    }
    $name = "StartDate={$startDate}_EndDate={$endDate}{$reportOptionsInName}";

    return [$prefix, "{$name}.{$ext}"];
}

function getReportTypesToDownload($profileType, $givenReportTypes = [])
{
    $profileType = getProfileType($profileType);

    $allReportEntities = ETLHelper::allReportsConfiguration($profileType);

    $reportTypes = [];

    if (!is_array($givenReportTypes)) {
        if (!empty($givenReportTypes)) {
            $givenReportTypes = explode(',', $givenReportTypes);
        } else {
            $givenReportTypes = [];
        }
    }

    if (count($givenReportTypes) > 0) {

        foreach ($givenReportTypes as $givenReportEntity) {
            $givenReportEntity = trim($givenReportEntity);
            $checkArray = iCheckInArray($givenReportEntity, $allReportEntities);
            if ($checkArray !== -1) {
                $reportTypes[] = $checkArray;
            }
        }

    } else {
        $reportTypes = $allReportEntities;
    }
    return $reportTypes;
}

function getLambdaClient()
{
    $config = [
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'region' => env('AWS_DEFAULT_REGION'),
        'version' => 'latest',
    ];
    $client = new LambdaClient($config);
    return $client;
}

function invokeLambda($lambdaPayload)
{
    $args = [
        'FunctionName' => 'sp-api-create-tables',
        'InvocationType' => 'Event',
        'Payload' => json_encode($lambdaPayload)
    ];
    $client = getLambdaClient();

    try {
        Log::info("Invoking Lambda [" . @$args['FunctionName'] . "]");
        Log::info($args);
        $lambda_resp = $client->invoke($args);
        $lambda_resp = $lambda_resp->toArray();
        Log::info($lambda_resp);
        $resp = [
            'status' => true,
            'lambda_output' => $lambda_resp
        ];
    } catch (\Exception $e) {
        $resp = [
            "status" => false,
            "error" => $e->getMessage()
        ];
        Log::error($e->getMessage());
//        notifyBugsnagError($e, $resp);
    }
    return $resp;
}
