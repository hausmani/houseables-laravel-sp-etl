<?php

namespace App\Console\Commands\Report;

use App\Jobs\DataCollection\Report\RequestReportJob;
use Illuminate\Console\Command;

class DownloadReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:report
        {--p= : DB Profile IDs comma separated}
        {--cid= : DB Client IDs comma separated}
        {--profile_type= : Profile Type}
        {--reports= : Reports}
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
        $cid = $this->option('cid') ? $this->option('cid') : '';
        $profile_types = $this->option('profile_type') ? $this->option('profile_type') : '';
        $reports = $this->option('reports') ? $this->option('reports') : '';

        $skip_profile = $this->option('skip_profile') ? $this->option('skip_profile') : '';

        $customDateRange = $this->option('customDateRange') ? $this->option('customDateRange') : '';
        $backfill = $this->option('backfill') ? $this->option('backfill') : '';
        $reportRange = $this->option('reportRange') ? $this->option('reportRange') : '';

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        $profile_types = parseProfileTypeArg($profile_types);
        foreach ($profile_types as $profile_type) {

            $profilesForDataDownload = getProfilesForDataDownload($profile_type, $profile_id, true, $cid);
            $skip_acc = empty($skip_acc) ? [] : explode(',', $skip_acc);
            $skip_profile = empty($skip_profile) ? [] : explode(',', $skip_profile);

            foreach ($profilesForDataDownload as $profile) {

                if (in_array($profile->id, $skip_profile)) {
                    $this->warn('Skipping [download:report] command for ' . $profile->profile_type . ' and profile ' . $profile->id);
                    continue;
                }

                $this->info('Running [download:report] command for type ' . $profile->profile_type . ' and profile ' . $profile->id);

                $profile_info = [
                    'profile_id' => $profile->id,
                    'client_id' => $profile->client_id,
                    'profile_type' => $profile->profile_type,
                    'client_authorisation_id' => $profile->client_authorisation_id,
                    'marketplaceId' => $profile->marketplaceId,
                    'countryCode' => $profile->countryCode,
                    'profileId' => $profile->profileId,
                    'sellerId' => $profile->sellerId,
                    'inactive_reports' => $profile->inactive_reports,
                    'retry_attempts' => 1
                ];

                $job = new RequestReportJob($profile_info, $reports, $backfill, $customDateRange, $reportRange);

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
