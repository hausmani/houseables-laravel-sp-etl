<?php

namespace App\Console\Commands;

use App\Jobs\TestReportJob;
use Illuminate\Console\Command;


class DispatchTestReportJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for Test job ecs';

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
        $job = new TestReportJob();
        $dispatched = dispatch($job);
        $this->info("Job is dispatched");
    }
}
