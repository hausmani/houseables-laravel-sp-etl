<?php

namespace App\Console\Commands\FBAInbound;

use App\Jobs\DataCollection\FBAInbound\GetFBAInboundShipmentsJob;
use App\TE\HelperClasses\ETLHelper;
use Illuminate\Console\Command;

class GetFBAInboundShipmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:fba_inbound_shipments
        {--p= : DB Profile IDs comma separated}
        {--cid= : DB Client IDs comma separated}
        {--profile_type= : Profile Type}
        {--backfill= : Backfill ("historical", "restatement", "custom" or "smart" along with "customDateRange", rr any duration like "1 year", "15 Months", "10 Weeks")}
        {--customDateRange= : Custom Date Range}
        {--reportRange= : Report Range to be requested in one file}
        {--skip_profile= : Skipped profile IDs comma separated}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for getting FBA inbound shipments for profiles from API';

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
        $cid = $this->option('cid') ? $this->option('cid') : '';
        $profile_types = $this->option('profile_type') ? $this->option('profile_type') : '';

        $customDateRange = $this->option('customDateRange') ? $this->option('customDateRange') : '';
        $backfill = $this->option('backfill') ? $this->option('backfill') : '';
        $reportRange = $this->option('reportRange') ? $this->option('reportRange') : '';

        $skip_profile = $this->option('skip_profile') ? $this->option('skip_profile') : '';

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        $profile_types = parseProfileTypeArg($profile_types);
        foreach ($profile_types as $profile_type) {

            $profilesForDataDownload = getProfilesForDataDownload($profile_type, $profile_id, true, $cid);
            $skip_acc = empty($skip_acc) ? [] : explode(',', $skip_acc);
            $skip_profile = empty($skip_profile) ? [] : explode(',', $skip_profile);

            foreach ($profilesForDataDownload as $profile) {

                if (in_array($profile->id, $skip_profile)) {
                    $this->warn('Skipping [download:fba_inbound_shipments] command for ' . $profile->profile_type . ' and profile ' . $profile->id);
                    continue;
                }

                $this->info('Running [download:fba_inbound_shipments] command for type ' . $profile->profile_type . ' and profile ' . $profile->id);

                $profile_info = [
                    'profile_id' => $profile->id,
                    'client_id' => $profile->client_id,
                    'profile_type' => $profile->profile_type,
                    'client_authorisation_id' => $profile->client_authorisation_id,
                    'marketplaceId' => $profile->marketplaceId,
                    'countryCode' => $profile->countryCode,
                    'profileId' => $profile->profileId,
                    'sellerId' => $profile->sellerId,
                    'retry_attempts' => 1
                ];

                $dateRanges = ETLHelper::getApiPatterns($profile_info['profile_type'], FBA_INBOUND_SHIPMENT, $backfill, $customDateRange, $reportRange, "ASC");

                foreach ($dateRanges as $dateRange) {

                    $updatedAfter = $dateRange[0];
                    $updatedBefore = $dateRange[1];

                    $job = new GetFBAInboundShipmentsJob($profile_info, $updatedAfter, $updatedBefore);

                    if (!empty($queue_name)) {
                        $job->onQueue($queue_name);
                    }

                    $job->switchToTestQueueIfTestServer();

                    if (!empty($conn)) {
                        $job->onConnection($conn);
                    }

                    dispatch($job);
                }
            }
        }
    }
}
