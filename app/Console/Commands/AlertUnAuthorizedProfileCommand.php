<?php

namespace App\Console\Commands;

use App\Jobs\DataCollection\Report\RequestReportJob;
use App\Services\SlackNotification;
use App\TE\HelperClasses\MyRedis;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AlertUnAuthorizedProfileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:unauthorized
        {--c= : DB Client IDs comma separated}
        {--p= : DB Profile IDs comma separated}
        {--profile_type= : Profile Type}
        {--skip_profile= : Skipped profile IDs comma separated}
        {--conn=sync : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for testing the profiles and mark them unauthorized if access is revoked';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function formatTable(array $headers, array $rows): array
    {
        $maxBlocks = 50; // Slack allows a maximum of 50 blocks per message
        $maxFieldsPerSection = 10; // Maximum fields per section block
        $blocks = [];

        // Create header block
        $headerFields = array_map(fn($header) => [
            "type" => "mrkdwn",
            "text" => "*{$header}*"
        ], $headers);

        $blocks[] = [
            "type" => "header",
            "text" => [
                "type" => "plain_text",
                "text" => "📊 Client with Access Revoked",
                "emoji" => true
            ]
        ];

        $blocks[] = [
            "type" => "section",
            "fields" => $headerFields
        ];

        $blocks[] = [
            "type" => "divider"
        ];

        // Add rows while respecting block limits
        foreach ($rows as $index => $row) {
            // Convert row to fields
            $rowFields = array_map(fn($value) => [
                "type" => "mrkdwn",
                "text" => (string)$value
            ], $row);

            // Add row fields in chunks of $maxFieldsPerSection
            $chunks = array_chunk($rowFields, $maxFieldsPerSection);
            foreach ($chunks as $chunk) {
                $blocks[] = [
                    "type" => "section",
                    "fields" => $chunk
                ];
                $blocks[] = [
                    "type" => "divider"
                ];

                // Stop adding blocks if we reach the limit
                if (count($blocks) >= $maxBlocks - 1) { // Reserve space for footer
                    break 2;
                }
            }
        }

        // Add a footer block if rows were truncated
        if (count($blocks) >= $maxBlocks - 1) {
            $blocks[] = [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "⚠️ Some rows were truncated due to Slack's block limit."
                ]
            ];
        }

        return $blocks;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {


        $profile_id = $this->option('p') ? $this->option('p') : '';
        $c = $this->option('c') ? $this->option('c') : '';
        $profile_types = $this->option('profile_type') ? $this->option('profile_type') : '';
        $queue_name = $this->option('q') ? $this->option('q') : '';
        $conn = $this->option('conn') ? $this->option('conn') : 'sync';
        $profilesForDataDownload = [];

        $profile_types = parseProfileTypeArg($profile_types);
        foreach ($profile_types as $profile_type) {

            $profilesForDataDownload = getProfilesForDataDownload($profile_type, $profile_id);
            $skip_acc = empty($skip_acc) ? [] : explode(',', $skip_acc);
            $skip_profile = empty($skip_profile) ? [] : explode(',', $skip_profile);

            foreach ($profilesForDataDownload as $profile) {

                if (in_array($profile->id, $skip_profile)) {
                    $this->warn('Skipping [alert:unauthorized] command for ' . $profile->profile_type . ' and profile ' . $profile->id);
                    continue;
                }

                $this->info('Running [alert:unauthorized] command for type ' . $profile->profile_type . ' and profile ' . $profile->id);

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
                $reports = [SALES_AND_TRAFFIC_REPORT];
                $backfill = 'custom';
                $customDateRange = Carbon::now()->format("Ymd") . "," . Carbon::now()->format("Ymd");
                $reportRange = "";
                $sleepInJob = false;
                $job = new RequestReportJob($profile_info, $reports, $backfill, $customDateRange, $reportRange, $sleepInJob);
                $job->maxTries = 1;
                $job->setRedisCacheForInactiveAccounts = true;

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

        $profiles = MyRedis::redis_fetch_set_values('inactive_profiles');
        $table = [];
        foreach ($profiles as $jsonProfile) {
            $decodedProfile = json_decode($jsonProfile, true);
            foreach ($profilesForDataDownload as $profile) {
                if ($decodedProfile['ProfileId'] == $profile->id) {
                    $table[] = [
                        'ProfileId' => $profile->id,
                        //'clientId' => $profile->client_id,
                        //'ProfileType' => $profile->profile_type,
                        //'MarketplaceId' => $profile->marketplaceId,
                        //'CountryCode' => $profile->countryCode,
                        //'SellerId' => $profile->sellerId,
                        'ErrorCode' => $decodedProfile['ErrorCode'],
                    ];
                    break;
                }
            }

        }
        if (count($table) > 0) {
            $rowsChunks = array_chunk($table, 40); // Adjust chunk size to fit limits
            foreach ($rowsChunks as $chunk) {
                $table_tmp = $this->formatTable(array_keys($chunk[0]), $chunk);
                //$finalMarkDown = ['blocks' => $table_tmp];
                //echo "\n\n\n" . json_encode($finalMarkDown) . "\n\n\n";
                $slack = new SlackNotification();
                $slack->send($table_tmp);
            }
        }
    }
}
