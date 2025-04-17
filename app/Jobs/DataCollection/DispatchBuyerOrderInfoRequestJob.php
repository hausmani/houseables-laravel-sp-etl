<?php

namespace App\Jobs\DataCollection;

use Illuminate\Support\Facades\Artisan;

class DispatchBuyerOrderInfoRequestJob
{

    public function handle(array $data)
    {
        $default = [
            'cid' => '',
            'pid' => '',
            'sid' => '',
            'mid' => '',
            'oid' => '',
            'purchase_daterange' => '',
            'rows_limit' => '',
            'chunk_size' => ''
        ];

        $params = [];
        foreach ($default as $key => $defaultValue) {
            $params['--' . $key] = empty($data[$key]) ? $default[$key] : $data[$key];;
        }
        Artisan::call('download:orders:buyerinfo', $params);
    }
}
