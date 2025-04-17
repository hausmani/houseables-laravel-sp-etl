<?php

namespace App\Console\Commands;

use App\Jobs\DataCollection\Report\RequestReportJob;
use Illuminate\Console\Command;

class CreateClientTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:tables
        {--c= : DB Client IDs comma separated}
        {--profile_type= : Profile Type}
        {--reports= : Reports}
        {--action= : Reports}
        {--conn= : Connection for Queue (sqs, sync, database)}
        {--q= : Queue Name}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for creating tables for client';

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

        $c = $this->option('c') ? $this->option('c') : '';
        $profile_types = $this->option('profile_type') ? $this->option('profile_type') : '';
        $reportTypes = $this->option('reports') ? $this->option('reports') : '';
        $action = $this->option('action') ? $this->option('action') : 'create_table';
        $action = empty($action) ? 'create_table' : $action;

        $profile_types = parseProfileTypeArg($profile_types);

        $profile_types = te_compare_strings($action, 'fix_schema') ? [''] : $profile_types;

        foreach ($profile_types as $profile_type) {

            $clients = getClientsForDataDownload($c, $profile_type);

            if (te_compare_strings($action, 'fix_schema')) {
                $reportTypes = getReportTypesToDownload(PROFILE_SELLER_CENTRAL, $reportTypes);
                $vReportTypes = getReportTypesToDownload(PROFILE_VENDOR_CENTRAL, $reportTypes);
                $reportTypes = array_merge($reportTypes, $vReportTypes);
//                if (isSellerCentral($profile_type)) {
//                    $reportTypes[] = ORDERS_BUYER_INFO;
//                }
            } else {
                $reportTypes = getReportTypesToDownload($profile_type, $reportTypes);
//                if (isSellerCentral($profile_type)) {
//                    $reportTypes[] = ORDERS_BUYER_INFO;
//                }
            }

            foreach ($clients as $client) {

                $client_id = $client->client_id;
                $this->info("Running [create:tables] command for  action={$action} , type={$profile_type} and client_id={$client_id}");

                $lambda_input = [
                    'action' => $action,
                    'profile_type' => $profile_type,
                    'client_id' => "{$client_id}",
                    "reportTypes" => $reportTypes
                ];

                $resp = invokeLambda($lambda_input);

            }
        }
    }
}
