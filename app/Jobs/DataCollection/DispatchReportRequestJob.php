<?php

namespace App\Jobs\DataCollection;

use Illuminate\Support\Facades\Artisan;

class DispatchReportRequestJob
{

    public function handle(array $data)
    {

        $default = [
            'p' => '',
            'reports' => '',
            'profile_type' => '',
            'backfill' => 'restatement',
            'customDateRange' => '',
            'reportRange' => '',
            'skip_profile' => ''
        ];

        $params = [];
        foreach ($default as $key => $defaultValue) {
            $params['--' . $key] = empty($data[$key]) ? $default[$key] : $data[$key];;
        }
        Artisan::call('download:report', $params);
    }
}
