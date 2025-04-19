<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;


abstract class Job
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "onQueue" and "delay" queue helper methods.
    |
    */

    use Dispatchable, Queueable;

    public function onQueue($queue)
    {
        $this->queue = $queue;
        return $this;
    }

    public function switchToTestQueueIfTestServer($testQueue = '')
    {
//            if (empty($testQueue)) {
//                $testQueue = $this->queue;
//                $testQueue = 'test-' . te_ltrim($testQueue, 'test-');
//            }
//            $this->onQueue($testQueue);
//            $this->onConnection('sqs');
//            if (is_local_environment()) {
//                $this->onConnection('database');
//            }
    }
}
