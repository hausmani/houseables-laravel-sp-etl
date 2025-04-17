<?php

function p_rr($data, $exit = 0)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    if ($exit == 1) {
        exit;
    }
}

if (!function_exists('_dd')) {
    function _dd($arr)
    {
        $bt = debug_backtrace();
        $stackTrace = isset($bt[0]) ? $bt[0] : [];
        $stackTrace = is_array($stackTrace) ? $stackTrace : [];

        $stackTrace2 = isset($bt[1]) ? $bt[1] : [];
        $stackTrace2 = is_array($stackTrace2) ? $stackTrace2 : [];
        $dd_location = [
            'file' => @$stackTrace['file'],
            'function' => @$stackTrace2['function'],
            'line' => @$stackTrace['line']
        ];
        p_rr($dd_location);
        dd($arr);
    }
}

function camelFix($str)
{
    return preg_replace_callback('/(?<!\b)[A-Z][a-z]+|(?<=[a-z])[A-Z]/', function ($match) {
        return ' ' . $match[0];
    }, $str);
}

function te_wordify($str, $camelFix = false)
{
    if ($camelFix) {
        $str = camelFix($str);
    }
    return ucwords(str_replace('_', ' ', trim($str)));
}

if (!function_exists('date_range')) {
    function date_range($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
    {

        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {

            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }
}

if (!function_exists('te_compare_strings')) {
    function te_compare_strings($str1, $str2, $ignore_case = true)
    {
        if ($ignore_case) {
            $str1 = str_replace(' ', '', strtolower($str1));
            $str2 = str_replace(' ', '', strtolower($str2));
        }
        $str1 = trim($str1);
        $str2 = trim($str2);

        return $str1 == $str2;
    }
}

// To trim the exact string placed on left side of a string.
if (!function_exists('te_ltrim')) {
    function te_ltrim($str, $trimStr, $ignoreCase = false)
    {
        if ($ignoreCase) {
            $caseStr = strtolower($str);
            $caseTrimStr = strtolower($trimStr);
        } else {
            $caseStr = $str;
            $caseTrimStr = $trimStr;
        }

        $trimLength = strlen($trimStr);

        if (substr($caseStr, 0, $trimLength) == $caseTrimStr) {
            $str = substr($str, $trimLength);
        }

        return $str;
    }
}


// To trim the exact string placed on right side of a string.
if (!function_exists('te_rtrim')) {
    function te_rtrim($str, $trimStr, $ignoreCase = false)
    {
        if ($ignoreCase) {
            $caseStr = strtolower($str);
            $caseTrimStr = strtolower($trimStr);
        } else {
            $caseStr = $str;
            $caseTrimStr = $trimStr;
        }

        $trimLength = strlen($trimStr);

        if (substr($caseStr, -$trimLength) == $caseTrimStr) {
            $str = substr($str, 0, strlen($str) - $trimLength);
        }

        return $str;
    }
}

// To trim the exact string placed on both sides of a string.
if (!function_exists('te_trim')) {
    function te_trim($str, $trimStr, $ignoreCase = false)
    {
        $str = te_ltrim($str, $trimStr, $ignoreCase);
        $str = te_rtrim($str, $trimStr, $ignoreCase);
        return $str;
    }
}

function days_array_between_dates($dateStart, $dateEnd, $format = 'Y-m-d')
{

    $dateStart = date($format, strtotime($dateStart));
    $dateEnd = date($format, strtotime($dateEnd));

    $days = [];

    while (strtotime($dateStart) <= strtotime($dateEnd)) {
        $days[] = $dateStart;
        $dateStart = date('Y-m-d', strtotime($dateStart . ' +1 day'));
    }

    return $days;
}

if (!function_exists('array_insert_after')) {
    function array_insert_after(&$array, $position, $insert)
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos = array_search($position, array_keys($array));
            if ($pos !== false) {
                $array = array_merge(
                    array_slice($array, 0, $pos + 1),
                    $insert,
                    array_slice($array, $pos + 1)
                );
            }

        }
    }
}


function convertCollectionIntoArray($collection)
{
    $array = collect($collection)->map(function ($x) {
        return (array)$x;
    })->toArray();
    return $array;
}


function iCheckInArray($needle, $array)
{
    $matchedValueInArray = -1;
    foreach ($array as $value) {
        if (te_compare_strings($value, $needle)) {
            $matchedValueInArray = $value;
            break;
        }
    }
    return $matchedValueInArray;

}

function iMatchInArray($needle, $array)
{
    $matchedValueInArray = -1;
    /*
     * Special case for Entity as this is conflicting with Target Entity
     */
    $skipped_cols = ['Target Entity', 'Brand Entity Id', 'Entity Status'];
    if (stripos($needle, 'Entity') !== false && iCheckInArray($needle, $skipped_cols) === -1) {
        return 'Entity';
    }
    foreach ($array as $value) {
        if (te_compare_strings($value, 'Bulk Action')) {
            if (stripos($needle, $value) != false) {
                $matchedValueInArray = $value;
                break;
            }
        } else {
            if (te_compare_strings($value, $needle)) {
                $matchedValueInArray = $value;
                break;
            }
        }
    }
    return $matchedValueInArray;

}

function iCheckCommaValuesInArray($commaSepValues, $array, $sep = ',', &$matchedValues = [], &$unMatchedValues = [])
{
    $matchedValueInArray = -1;
    $finalMatchedArray = [];
    if (!is_array($commaSepValues)) {
        $commaSeparatedValuesArray = explode($sep, $commaSepValues);
    } else {
        $commaSeparatedValuesArray = $commaSepValues;
    }
    // trim spaces
    foreach ($commaSeparatedValuesArray as $index => $value) {
        $commaSeparatedValuesArray[$index] = trim($value);
    }
    $mismatchFound = false;
    $matchesValues = [];
    foreach ($commaSeparatedValuesArray as $value) {
        $found = iCheckInArray($value, $array);
        if ($found != -1) {
            $matchesValues[] = $found;
        } else {
            $unMatchedValues[] = $value;
//            $mismatchFound = true;
//            break;
        }
    }
    if (count($unMatchedValues) > 0) {
        $mismatchFound = true;
    }
    if (!$mismatchFound) {
        foreach ($array as $value) {
            $found = iCheckInArray($value, $matchesValues);
            if ($found != -1) {
                $finalMatchedArray[] = $value;
            }
        }
        $matchedValues = $finalMatchedArray;
        $matchedValueInArray = implode($sep, $finalMatchedArray);
    }
    return $matchedValueInArray;
}

if (!function_exists('createChunksOfData')) {
    function createChunksOfData($ApiData, $chunkSize)
    {
        $arrayChunks = [];
        if (count($ApiData) > $chunkSize) {
            $arrayChunks = array_chunk($ApiData, $chunkSize);
        } else {
            $arrayChunks[] = $ApiData;
        }

        return $arrayChunks;

    }
}

if (!function_exists('csv_to_array')) {
    function csv_to_array($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        $header = null;
        $data = array();
        ini_set('auto_detect_line_endings', true);
        if (($handle = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        return $data;
    }
}

if (!function_exists('database_lost_connection_messages')) {
    function database_lost_connection_messages()
    {
        return [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'Name or service not known',
            'ORA-03114',
            'Packets out of order. Expected',
            'Connection refused',
            'Communication link failure',
            'Connection timed out',
            'Too many connections',
            'Broken pipe',
        ];
    }
}

if (!function_exists('database_lock_timeout_connection_messages')) {
    function database_lock_timeout_connection_messages()
    {
        return [
            'Lock wait timeout exceeded',
            'Deadlock found when trying to get loc',
            'Table definition has changed',
        ];
    }
}

function isIntegerNumericVal($value)
{
    return preg_match('/^\d+$/', $value);
}


function print_table($data)
{
    $rowCount = 1;
    if (count($data) > 0) {
        ?>
        <table class="table table-bordered table-striped">
            <tr>
                <th>Sr.</th>
                <?php

                foreach ($data[0] as $headerIndex => $headercol) {
                    ?>
                    <th><?php echo $headerIndex; ?></th>
                    <?php
                }
                ?>
            </tr>
            <?php
            foreach ($data as $row) { ?>
                <tr>
                    <td><?php echo $rowCount++; ?></td>
                    <?php
                    foreach ($row as $val) {
                        ?>
                        <td><?php echo $val; ?></td>
                        <?php
                    }
                    ?>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    }
}
