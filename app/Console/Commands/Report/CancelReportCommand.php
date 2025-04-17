<?php

namespace App\Console\Commands\Report;

use App\Jobs\DataCollection\Report\CancelReportJob;
use Illuminate\Console\Command;

class CancelReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel:report
        {--p= : DB Profile IDs comma separated}
        {--profile_type= : Profile Type}
        {--reports= : Reports}
        {--processingStatus= : processingStatus}
        {--createdSince= : createdSince}
        {--createdUntil= : createdUntil}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for cancelling reports for channels from API';

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
        $profile_types = $this->option('profile_type') ? $this->option('profile_type') : '';
        $reports = $this->option('reports') ? $this->option('reports') : '';
        $processingStatus = $this->option('processingStatus') ? $this->option('processingStatus') : 'IN_QUEUE';

        $createdSince = $this->option('createdSince') ? $this->option('createdSince') : date("Y-m-d", strtotime("-7 day"));
        $createdUntil = $this->option('createdUntil') ? $this->option('createdUntil') : date("Y-m-d");

        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : '';

        if (empty($profile_types)) {
            $this->error("Profile Type(s) not specified.");
            return;
        }

        $profile_types = parseProfileTypeArg($profile_types);
        foreach ($profile_types as $profile_type) {

            $profilesForDataDownload = getProfilesForDataDownload($profile_type, $profile_id);

            $skip_acc = empty($skip_acc) ? [] : explode(',', $skip_acc);
            $skip_profile = empty($skip_profile) ? [] : explode(',', $skip_profile);
            foreach ($profilesForDataDownload as $profile) {


                $this->info('Running [cancel:report] command for type ' . $profile->profile_type . ' and profile ' . $profile->id);

                $profile_info = [
                    'profile_id' => $profile->id,
                    'client_id' => $profile->client_id,
                    'profile_type' => $profile->profile_type,
                    'client_authorisation_id' => $profile->client_authorisation_id,
                    'marketplaceId' => $profile->marketplaceId,
                    'profileId' => $profile->profileId,
                    'sellerId' => $profile->sellerId,
                ];
                $job = new CancelReportJob($profile_info, $reports, $processingStatus, $createdSince, $createdUntil);

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
