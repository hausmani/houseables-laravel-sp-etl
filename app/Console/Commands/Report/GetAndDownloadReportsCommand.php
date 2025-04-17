<?php

namespace App\Console\Commands\Report;

use App\Jobs\DataCollection\Report\GetReportsJob;
use Illuminate\Console\Command;

class GetAndDownloadReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:download:reports
        {--p= : DB Profile IDs comma separated}
        {--cid= : DB Client IDs comma separated}
        {--profile_type= : Profile Type}
        {--reports= : Reports}
        {--createdSince= : created since date}
        {--createdUntil= : created until date}
        {--processingStatuses= : processing status}
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

        if (empty($reports)) {
            $this->error("report is not mentioned.");
        }

        $skip_profile = $this->option('skip_profile') ? $this->option('skip_profile') : '';

        $createdSince = $this->option('createdSince') ? $this->option('createdSince') : '';
        $createdUntil = $this->option('createdUntil') ? $this->option('createdUntil') : '';
        $processingStatuses = $this->option('processingStatuses') ? $this->option('processingStatuses') : 'DONE';

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

                $job = new GetReportsJob($profile_info, $reports, $processingStatuses, $createdSince, $createdUntil);

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
