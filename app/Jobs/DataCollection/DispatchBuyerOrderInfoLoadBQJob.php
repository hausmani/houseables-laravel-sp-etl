<?php

namespace App\Jobs\DataCollection;

use Illuminate\Support\Facades\Artisan;

class DispatchBuyerOrderInfoLoadBQJob
{

    public function handle(array $data)
    {
        $default = [
            'c' => ''
        ];

        $params = [];
        foreach ($default as $key => $defaultValue) {
            $params['--' . $key] = empty($data[$key]) ? $default[$key] : $data[$key];;
        }
        Artisan::call('load:order:buyerinfo', $params);
    }
}
