<?php


namespace App\TE\HelperClasses;


use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     *
     * isCacheSet to check if a value is set in cache or not
     * @param string $key the name of the key to check
     */
    public static function isCacheSet($key)
    {
        $errorAlreadyPushed = Cache::get($key, '');
        return empty($errorAlreadyPushed) ? false : true;
    }

    /**
     *
     * setCache to set value for specific key
     * @param string $key the name of the key to check
     * @param string $value the value of the key
     * @param integer $time time to live
     */
    public static function setCache($key, $value, $time = 60)
    {
        Cache::put($key, $value, $time);
    }
}
