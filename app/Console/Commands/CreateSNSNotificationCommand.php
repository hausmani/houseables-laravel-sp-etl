<?php

namespace App\Console\Commands;

use App\Jobs\CreateSNSNotificationJob;
use Illuminate\Console\Command;

class CreateSNSNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:sns
        {--cid= : DB Client IDs comma separated}
        {--pid= : DB Profile Ids comma separated}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for creating SNS notifications for client/profiles';

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
        $conn = $this->option('conn') ? $this->option('conn') : '';
        $q = $this->option('q') ? $this->option('q') : '';

        $profile_ids = getProfilesForDataDownload(PROFILE_SELLER_CENTRAL, $pid, false, $cid);

        foreach ($profile_ids as $profile_id => $profile) {

            $this->info('Running [create:sns] command for client# ' . $profile->client_id . ' and Profile id# ' . $profile_id);

            $job = new CreateSNSNotificationJob($profile_id);
            if (!empty($conn)) {
                $job->onConnection($conn);
            }
            if (!empty($q)) {
                $job->onQueue($q);
            }

            dispatch($job);
        }
    }
}
