<?php

namespace App\Jobs\DataCollection;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Job as LaravelJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DispatchJobsJob
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue(Q_DISPATCH_JOB);
    }

    public function handle(LaravelJob $job, ?array $data)
    {

        $command = $data['command'] ?? '';
        if (empty($command)) {
            Log::error("Command is empty.");
            return;
        }

        $params = [];
        $_params = $data['params'] ?? [];
        foreach ($_params as $key => $value) {
            if (!empty($_params[$key])) {
                $params['--' . $key] = $_params[$key];
            }
        }
        Log::info("dispatching a command [$command] with params", $params);
        Artisan::call($command, $params);
    }

}
