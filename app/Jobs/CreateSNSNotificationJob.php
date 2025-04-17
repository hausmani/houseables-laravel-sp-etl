<?php

namespace App\Jobs;

use App\TE\HelperClasses\SpApiHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateSNSNotificationJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $profile_id;

    public function __construct($profile_id)
    {
        $this->profile_id = $profile_id;
        $this->onQueue(Q_DEFAULT);
    }

    /**
     * handle job.
     *
     * @return void
     */
    public function handle()
    {
        SpApiHelper::createSubscription($this->profile_id);
    }
}
