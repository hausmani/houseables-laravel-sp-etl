<?php

namespace App\TE\HelperClasses;

use Illuminate\Support\Facades\Redis;

class MyRedis
{
    public static function publish_redis_msg($redis_msg)
    {
        $channel = $redis_msg['channel'];
        $msg = $redis_msg['msg'];
        $msg['app_env'] = env('APP_ENV', 'local');
        $encoded_msg = json_encode($msg);
        try {
            Redis::publish($channel, $encoded_msg);
        } catch (\Exception $e) {
            notifyBugsnagError($e->getMessage() . ' in publish redis msg', $msg);
        }
    }

    /**
     * Executes the add value to the set
     *
     * @param string $key unique key of the set
     * @param string $value value the set
     * @param integer|string $ttl number of seconds the value should live
     *
     * @return bool 1 if value added to the set OR 0 is already/already exists
     */
    public static function redis_insert_set_value($key, $value = '', $ttl = '')
    {
        $return = 0;
        try {
            $return = Redis::sadd($key, $value);

        } catch (\Exception $exp) {
            $return = 0;
        }
        //only set ttl if we successfully inserted value inside set and time to live is not empty
        if ($return != 0 && !empty($ttl)) {
            try {
                Redis::expire($key, $ttl);
            } catch (\Exception $exp) {
            }
        }
        return $return;
    }

    /**
     * Executes the add value to the set
     *
     * @param string $key unique key of the set
     * @param string $value value the set
     * @param integer|string $ttl number of seconds the value should live
     *
     * @return bool 1 if value added to the set OR 0 is already/already exists
     */
    public static function scan_keys($keyword)
    {
        $return = [];
        try {
            $return = Redis::keys($keyword);
        } catch (\Exception $exp) {
            $return = [];
        }

        return $return;
    }

    /**
     * Fetch the values inside the set
     *
     * @param string $key unique key of the set
     *
     * @return mixed array of values
     */
    public static function redis_fetch_set_values($key)
    {
        $redis_value = false;
        try {
            $redis_value = Redis::smembers($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = false;
        }
        $redis_value = $redis_value ? $redis_value : [];
        return $redis_value;
    }

    /**
     * count the elements inside the set
     *
     * @param string $key unique key of the set
     *
     * @return integer number of elements in the set
     */

    public static function redis_fetch_set_count($key)
    {
        $redis_value = false;
        try {
            $redis_value = Redis::scard($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = false;
        }
        $redis_value = $redis_value ? $redis_value : 0;
        return $redis_value;
    }

    /**
     * remove a element from the set
     *
     * @param string $key unique key of the set
     * @param string $value value the set
     * @return integer|string the value of the set element
     */

    public static function redis_remove_set_element($key, $value)
    {
        $redis_value = false;
        try {
            $redis_value = Redis::srem($key, $value);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = false;
        }
        $redis_value = $redis_value ? $redis_value : 0;
        return $redis_value;
    }

    public static function redis_remove_set_elements($key, array $values)
    {
        $removed_count = 0;
        try {
            $removed_count = Redis::srem($key, ...$values);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'values' => $values,
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $removed_count = 0;
        }
        return $removed_count;
    }

    /**
     * count total elements in the set
     *
     * @param string $key unique key of the set
     * @param string $value value the set
     * @return integer|string the value of the set element
     */

    public static function redis_count_set_elements($key)
    {
        $redis_value = 0;
        try {
            $redis_value = Redis::scard($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = 0;
        }
        return $redis_value;
    }

    /**
     * remove the set
     *
     * @param string $key unique key of the set
     * @param string $value value the set
     * @return integer|string the value of the set element
     */

    public static function delete_key($key)
    {
        $redis_value = false;
        try {
            $redis_value = Redis::del($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = false;
        }
        $redis_value = $redis_value ? $redis_value : 0;
        return $redis_value;
    }

    public static function set_value($key, $value = '', $ttl = '')
    {
        $returnValue = $value;
        try {
            if (!empty($ttl)) {
                Redis::set($key, $value, 'EX', $ttl);
            } else {

                Redis::set($key, $value);
            }
        } catch (\Exception $e) {
            $returnValue = false;
            $_error = [
                'key' => $key,
                'value' => $value,
                'error message' => 'Error in set_value() -> ' . $e->getMessage()
            ];
            notifyBugsnagError($e, $_error, 'info');
        }
        return $returnValue;
    }

    public static function get_value($key, $default = false)
    {
        $errorInRedis = false;
        try {
            $redis_value = Redis::get($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'error message' => 'Error in get_value(' . $key . ') -> ' . $exp->getMessage()
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $redis_value = null;
            $errorInRedis = true;
        }
        $redis_value = is_null($redis_value) ? $default : $redis_value;
        return [$errorInRedis, $redis_value];
    }

    public static function redis_hvals($key)
    {
        $values = [];
        try {
            $values = Redis::hvals($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'function' => 'hvals',
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $values = [];
        }
        return $values;
    }

    public static function redis_hkeys($key)
    {
        $keys = [];
        try {
            $keys = Redis::hkeys($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'function' => 'hkeys',
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $keys = [];
        }
        return $keys;
    }

    public static function redis_hget($key, $field)
    {
        $value = '';
        try {
            $value = Redis::hget($key, $field);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'field' => $field,
                'function' => 'hget',
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $value = '';
        }
        return $value;
    }

    public static function redis_hset($key, $value)
    {
        $returnValue = '';
        try {
            $returnValue = Redis::hset($key, $value);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'value' => $returnValue,
                'function' => 'hget',
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $returnValue = '';
        }
        return $returnValue;
    }

    public static function redis_hgetall($key)
    {
        $value = '';
        try {
            $value = Redis::hgetall($key);
        } catch (\Exception $exp) {
            $_error = [
                'key' => $key,
                'function' => 'hgetall',
            ];
            notifyBugsnagError($exp, $_error, 'info');
            $value = '';
        }
        return $value;
    }

}
