<?php

namespace App\Jobs\DataCollection;

use Illuminate\Support\Facades\Artisan;

class DispatchNewDataRequestJob
{

    public function handle(array $data)
    {

        $default = [
            'p' => '',
            'reports' => '',
        ];

        $params = [];
        foreach ($default as $key => $defaultValue) {
            $params['--' . $key] = empty($data[$key]) ? $default[$key] : $data[$key];;
        }
        Artisan::call('check:request:new', $params);
    }
}
