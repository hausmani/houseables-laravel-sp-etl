<?php

namespace App\Console\Commands\AWD;

use App\Jobs\DataCollection\AWD\ListInventoryJob;
use Illuminate\Console\Command;

class ListInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:awd_inventory
        {--p= : DB Profile IDs comma separated}
        {--cid= : DB Client IDs comma separated}
        {--profile_type= : Profile Type}
        {--skip_profile= : Skipped profile IDs comma separated}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for listing awd inventory for profiles from API';

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
                    $this->warn('Skipping [download:awd_inventory] command for ' . $profile->profile_type . ' and profile ' . $profile->id);
                    continue;
                }

                $this->info('Running [download:awd_inventory] command for type ' . $profile->profile_type . ' and profile ' . $profile->id);

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

//                config(['queue.default' => 'sqs']);
                dispatch(new ListInventoryJob($profile_info));

//                if (!empty($queue_name)) {
//                    $job->onQueue($queue_name);
//                }

//                if (!empty($conn)) {
//                    $job->onConnection($conn);
//                }

//                dispatch($job);

            }
        }
    }
}
