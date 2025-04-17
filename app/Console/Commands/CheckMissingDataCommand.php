<?php

namespace App\Console\Commands;

use App\Jobs\DataCollection\Report\RequestReportJob;
use App\Models\ClientProfile;
use App\TE\HelperClasses\BQHelper;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\ETLHelper;
use Carbon\Carbon;
use Google\Cloud\BigQuery\Date;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CheckMissingDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:missing:data
        {--cid= : DB Client IDs comma separated}
        {--pid= : DB Profile IDs comma separated}
        {--sid= : Seller IDs comma separated}
        {--reports= : Reports}
        {--date_range= : Date Range (YYYYMMDD,YYYYMMDD)}
        {--dispatch_job= : Yes or No}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for checking missing data in tables for client';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $cid = $this->option('cid') ? $this->option('cid') : '';
        $pid = $this->option('pid') ? $this->option('pid') : '';
        $sid = $this->option('sid') ? $this->option('sid') : '';
        if (empty($cid) && empty($pid) && empty($sid)) {
            $this->error("One of the client_id/profile_id/sellerId must be provided.");
            return;
        }

        $profiles = ClientProfile::whereIn("profile_type", [PROFILE_SELLER_CENTRAL, PROFILE_VENDOR_CENTRAL]);

        if (!empty($cid)) {
            $profiles = $profiles->where("client_id", $cid);
        }
        if (!empty($pid)) {
            $profiles = $profiles->where("id", $pid);
        }
        if (!empty($sid)) {
            $profiles = $profiles->where("sellerId", $sid);
        }

        $profiles = $profiles->get();

        $reports = $this->option('reports') ? $this->option('reports') : '';
        $date_range = $this->option('date_range') ? $this->option('date_range') : '';

        $start_date = "";
        $end_date = "";
        if (!empty($date_range)) {
            list($start_date, $end_date) = explode(",", $date_range . ',');
            $start_date = DateHelper::changeDateFormat($start_date, "Ymd", "Y-m-d");
            $end_date = DateHelper::changeDateFormat($end_date, "Ymd", "Y-m-d");
        }

        $dispatch_job = $this->option('dispatch_job') ? $this->option('dispatch_job') : 'no';

        foreach ($profiles as $profile) {

            $reports = getReportTypesToDownload($profile->profile_type, $reports);

            foreach ($reports as $report) {

                $this->info('Running [check:missing:data] command for profile ' . $profile->id . ', report ' . $report);

                list($start_date, $end_date) = $this->getStartAndEndDate($profile->profile_type, $report, $start_date, $end_date);

                $datesRanges = $this->getMissingDates($profile, $report, $start_date, $end_date);

                foreach ($datesRanges as $datesRange) {

                    $paramValues = [
                        '--p' => $profile->id,
                        '--reports' => $report,
                        '--profile_type' => $profile->profile_type,
                        '--backfill' => 'custom',
                        '--customDateRange' => $datesRange,
                        '--reportRange' => '',
                        '--skip_profile' => ''
                    ];

                    $paramStr = [];
                    foreach ($paramValues as $key => $value) {
                        $paramStr[] = $key . '=' . $value;
                    }

                    if (te_compare_strings($dispatch_job, 'yes')) {
                        $this->info("php artisan download:report " . implode(" ", $paramStr));
                        Log::info("php artisan download:report " . implode(" ", $paramStr));
                        Artisan::call('download:report', $paramValues);
                    } else {
                        $this->info("Profile[{$profile->id}] Report[{$report}] for MissingDates [{$datesRange }]");
                        Log::info("Profile[{$profile->id}] Report[{$report}] for MissingDates [{$datesRange }]");
                    }
                }
            }
        }
    }

    public function getMissingDates($profile, $report, $start_date, $end_date)
    {
        $dates = [];
        $query = $this->prepareQuery($profile, $report, $start_date, $end_date);
        if (!empty($query)) {
            list($queryStatus, $missingDatesResponse) = BQHelper::runQuery($query, true);
            if ($queryStatus) {
                foreach ($missingDatesResponse as $missing) {
                    $dates[] = str($missing['missing_date'])->value();
                }
            } else {
                Log::error("Error in BQ Query. \n [ {$query} ] FAILED.");
            }
        } else {
            Log::warning("Query not built for profile {$profile->id} -> report {$report}");
        }
        return DateHelper::getConsecutiveDateRangesFromDatesList($dates);
    }

    public function prepareQuery($profile, $report, $start_date, $end_date)
    {
        $client_id = $profile->client_id;
        $sellerId = $profile->sellerId;
        $marketplaceId = $profile->marketplaceId;
        $projectId = 'amazon-sp-report-loader';

        $config = [
            VENDOR_SALES_MANUFACTURING_REPORT => [
                'table' => "{$projectId}.retail_analytics.vendor_sales_retail_manufacturing_day_report_{$client_id}",
                'dateColumn' => "startDate"
            ],
            VENDOR_SALES_SOURCING_REPORT => [
                'table' => "{$projectId}.retail_analytics.vendor_sales_retail_sourcing_day_report_{$client_id}",
                'dateColumn' => "startDate"
            ],
            VENDOR_TRAFFIC_REPORT => [
                'table' => "{$projectId}.retail_analytics.vendor_traffic_day_report_{$client_id}",
                'dateColumn' => "startDate"
            ],
            SALES_AND_TRAFFIC_REPORT => [
                'table' => "{$projectId}.retail_analytics.sales_and_traffic_child_day_report_{$client_id}",
                'dateColumn' => "startDate"
            ],
            LEDGER_SUMMARY_VIEW_DATA => [
                'table' => "{$projectId}.fba.ledger_summary_view_country_daily_data_{$client_id}",
                'dateColumn' => "startDate"
            ],
            FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_GENERAL => [
                'table' => "{$projectId}.orders.flat_file_all_orders_data_by_order_date_general_{$client_id}",
                'dateColumn' => "purchase_date"
            ],
            FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA => [
                'table' => "{$projectId}.fba.fba_fulfillment_customer_returns_data_{$client_id}",
                'dateColumn' => "return_date"
            ],
        ];

        $tableId = @$config[$report]['table'];
        $dateColumn = @$config[$report]['dateColumn'];
        $query = "";
        if (!empty($tableId)) {
            $query = "WITH pseudo_dates AS (
                        SELECT DATE(day) AS date FROM UNNEST(GENERATE_DATE_ARRAY(DATE('{$start_date}'), DATE('{$end_date}'))) AS day
                      ),
                      report_dates AS (
                        SELECT DATE({$dateColumn}) dateColumn, sellerId, marketplace
                        FROM `{$tableId}` WHERE marketplace='{$marketplaceId}' AND sellerId='{$sellerId}'
                        GROUP BY 1,2,3 ORDER BY dateColumn DESC
                      )
                    SELECT pd.date AS missing_date FROM pseudo_dates pd LEFT JOIN report_dates tbl
                    ON pd.date = tbl.dateColumn WHERE tbl.dateColumn IS NULL ORDER BY pd.date DESC;";
        }
        return $query;
    }

    public function getStartAndEndDate($profile_type, $report, $start_date, $end_date)
    {
        $pattern = ETLHelper::getApiPatterns($profile_type, $report, 'historical', '', '');

        $historical_end_date = @$pattern[0][1];
        $historical_start_date = @$pattern[count($pattern) - 1][0];

        if (empty($start_date)) {
            $start_date = $historical_start_date;
        } else {
            $start_date_obj = Carbon::parse($start_date);
            $historical_start_date_obj = Carbon::parse($historical_start_date);
            if ($start_date_obj->isBefore($historical_start_date_obj)) {
                $start_date = $historical_start_date_obj->toDateString();
            }
        }
        if (empty($end_date)) {
            $end_date = $historical_end_date;
        } else {
            $end_date_obj = Carbon::parse($end_date);
            $historical_end_date_obj = Carbon::parse($historical_end_date);
            if ($end_date_obj->isAfter($historical_end_date_obj)) {
                $end_date = $historical_end_date_obj->toDateString();
            }
        }
        return [$start_date, $end_date];
    }

}
