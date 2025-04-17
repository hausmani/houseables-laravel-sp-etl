<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Pm2ProcessList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pm2:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'stop all pm2 process';

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
     * @return mixed
     */
    public function handle()
    {
        $output = exec("pm2 jlist");
        $processIds = $this->parseJsonOfPm2($output);
        if (count($processIds) > 0) {
            foreach ($processIds as $id) {
                $command = "nohup pm2 stop " . $id . " >/dev/null 2>&1 &  ";
                $this->info($command);
                exec($command, $out);
            }
        } else {
            $this->info("No process is running");
        }
    }

    private function parseJsonOfPm2($json)
    {
        $ids = [];
        if (!empty($json)) {
            $list = json_decode($json, true);
            if ($list) {
                foreach ($list as $oneProcess) {
                    $ids[] = $oneProcess['pm_id'];
                }
            }
        }
        return $ids;
    }

}
