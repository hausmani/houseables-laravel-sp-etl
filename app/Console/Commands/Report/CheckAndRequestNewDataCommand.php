<?php

namespace App\Console\Commands\Report;

use App\Jobs\DataCollection\Report\RequestReportJob;
use App\Models\ClientProfile;
use App\TE\HelperClasses\DateHelper;
use App\TE\HelperClasses\MyRedis;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAndRequestNewDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:request:new
        {--p= : DB Profile IDs comma separated}
        {--reports= : Reports}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for downloading reports for channels from API';

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

        $profile_id = $this->option('p') ? $this->option('p') : '';
        $reports = $this->option('reports') ? $this->option('reports') : '';

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        if (empty($profile_id)) {
            $keys = MyRedis::scan_keys("NEWDATA_*");
            Log::info($keys);
            if (count($keys) > 0) {
                foreach ($keys as $key) {
                    list($reportType, $client_id, $marketplaceId, $sellerId, $date) = $this->extract_values_from_redis_key($key);
                    if ($date == Carbon::yesterday()->toDateString()) {
                        $profile = ClientProfile::where("client_id", $client_id)->where("sellerId", $sellerId)->where("marketplaceId", $marketplaceId)->first();
                        if ($profile) {

                            $profile_info = [
                                'profile_id' => $profile->id,
                                'client_id' => $profile->client_id,
                                'profile_type' => $profile->profile_type,
                                'client_authorisation_id' => $profile->client_authorisation_id,
                                'marketplaceId' => $profile->marketplaceId,
                                'profileId' => $profile->profileId,
                                'sellerId' => $profile->sellerId,
                                'inactive_reports' => $profile->inactive_reports,
                                'retry_attempts' => 1
                            ];

                            $date = date("Ymd", strtotime($date));
                            $job = new RequestReportJob($profile_info, [$reportType], 'custom', "{$date},{$date}", '');
                            if (!empty($conn)) {
                                $job->onConnection($conn);
                            }
                            if (!empty($queue_name)) {
                                $job->onQueue($queue_name);
                            } else {
                                $job->onQueue(Q_SELLER_REPORT_REQUEST_API_NEW);
                            }
                            dispatch($job);

                        } else {
                            Log::info("Profile NOT FOUND for client_id = {$client_id}, marketplaceId = {$marketplaceId}, sellerId = {$sellerId}");
                        }
                    } else {
                        MyRedis::delete_key($key);
                    }
                }
            }
        }
    }

    protected function extract_values_from_redis_key($key)
    {
        // NEWDATA_{self.report_type}__{self.client_id}_{self.marketplace}_{self.seller_id}_{yesterday}

        $exploded = explode("__", $key);
        $reportType = @$exploded[0];
        $reportType = str_replace("NEWDATA_", "", $reportType);

        $ids_and_date = @$exploded[1];
        $ids_and_date_exploded = explode("_", $ids_and_date);
        $client_id = @$ids_and_date_exploded[0];
        $marketplaceId = @$ids_and_date_exploded[1];
        $sellerId = @$ids_and_date_exploded[2];
        $date = @$ids_and_date_exploded[3];

        return [$reportType, $client_id, $marketplaceId, $sellerId, $date];
    }
}
