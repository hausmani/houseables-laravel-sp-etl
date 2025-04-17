<?php

namespace App\Console\Commands\Report;

use App\TE\HelperClasses\ETLHelper;
use App\TE\QuerySQS;
use Illuminate\Console\Command;
use Monolog\Handler\RedisHandler;

class GetSellerReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:sellerReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for getting dummy notification of seller report on SQS queue';

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
        $reportDetails = [
            'sellerId' => '',
            'reportType' => '',
            'reportId' => '',
            'reportDocumentId' => '',
        ];
        sendReportStatusNotificationOnSQS($reportDetails);
    }
}
