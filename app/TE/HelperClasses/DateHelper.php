<?php


namespace App\TE\HelperClasses;


use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DateHelper
{
    public static function getDateArrayBetweenTwoDates($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
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

    public static function changeDateFormat($date_str, $existing_format, $new_format)
    {
        $new_date = '';
        try {
            $new_date = Carbon::createFromFormat($existing_format, $date_str)->format($new_format);
        } catch (\Exception $e) {
            $new_date = '';
        }

        return $new_date;
    }

    public static function getDateRangesBetweenTwoDates($startDate, $endDate, $step = "1 Day", $output_format = 'Y-m-d', $selected_day = '')
    {
        $stepUnit = "days";
        if (preg_match('/(\d+)\s*(Year|Month|Week|Day)s?/i', $step, $matches)) {
            $step = (int)$matches[1];
            $unit = strtolower($matches[2]);
            switch ($unit) {
                case 'year':
                    $stepUnit = "years";
                    break;
                case 'month':
                    $stepUnit = "months";
                    break;
                case 'week':
                    $stepUnit = "weeks";
                    break;
                case 'day':
                    $stepUnit = "days";
                    break;
                default:
                    $stepUnit = 'days';
                    break;
            }
        }

        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        $dates = array();

        // starting from $endDate and ending at $startDate
        while ($endDate >= $startDate) {

            if ($selected_day == 'sunday' || $selected_day == 'last_sunday') {
                $dates[] = [date($output_format, $endDate), date($output_format, $endDate)];
                $endDate = strtotime("-7 days", strtotime(date('Y-m-d', $endDate)));

            } else {

                $_startDate = date($output_format, strtotime("+1 day", strtotime("-$step $stepUnit", $endDate)));
                $endDate = date($output_format, $endDate);

                if (strtotime($_startDate) < $startDate) {
                    $_startDate = date("Y-m-d", $startDate);
                }

                $dates[] = [$_startDate, $endDate];
                $endDate = strtotime("-1 day", strtotime($_startDate));
            }
        }

        return $dates;
    }

    public static function validateDate($date_str)
    {

        $match = false;
        if (preg_match("/^(0[1-9]|1[0-2]|[1-9])\/(0[1-9]|[1-2][0-9]|3[0-1]|[1-9])\/([0-9]{4}|[0-9]{2})$/", $date_str)) {
            $match = date('m/d/Y', strtotime($date_str));
        }

        return $match;
    }

    public static function formatDateISO8601($dateStr, $endOfDay = false)
    {
        $time = $endOfDay ? '23:59:59' : '00:00:00';
        return date("Y-m-d", strtotime($dateStr)) . "T{$time}Z";
    }

    public static function getDurationValue($durationStr)
    {
        if (preg_match('/(\d+)\s*(Year|Month|Week|Day)s?/i', $durationStr, $matches)) {
            $quantity = (int)trim($matches[1]);
            $unit = $matches[2];
        } else {
            $quantity = (int)trim($durationStr);
            $unit = "Day";
        }
        return [$quantity, strtolower(trim($unit))];
    }

    public static function getDateByDuration($endDate, $duration)
    {
        try {
            $endDateObj = Carbon::parse($endDate);
            list($durationValue, $unit) = self::getDurationValue($duration);

            switch ($unit) {
                case 'year':
                case 'years':
                    $startDate = $endDateObj->subYears($durationValue)->addDay()->toDateString();
                    break;
                case 'month':
                case 'months':
                    $startDate = $endDateObj->subMonths($durationValue)->addDay()->toDateString();
                    break;
                case 'week':
                case 'weeks':
                    $startDate = $endDateObj->subWeeks($durationValue)->addDay()->toDateString();
                    break;
                case 'day':
                case 'days':
                    $startDate = $endDateObj->subDays($durationValue)->addDay()->toDateString();
                    break;
                default:
                    $startDate = $endDate;
                    break;
            }

        } catch (\Exception $e) {
            Log::error($e);
            $startDate = $endDate;
        }
        return $startDate;
    }

    public static function getLastSaturday($date = '')
    {
        // end date should be the last saturday if today is Tuesday or onwards.
        $date = empty($date) ? date("Y-m-d") : $date;
        $dayNum = (int)date('w', strtotime($date));
        $daysAfterSaturday = $dayNum >= 2 ? $dayNum + 1 : $dayNum + 8;
        return date("Y-m-d", strtotime("-$daysAfterSaturday days", strtotime($date)));
    }

    public static function getRecentSunday($date = '')
    {
        $date = empty($date) ? date("Y-m-d") : $date;
        $dayNum = (int)date('w', strtotime($date));
        return date("Y-m-d", strtotime("-{$dayNum} days", strtotime($date)));
    }

    public static function isSunday($date = '')
    {
        $date = empty($date) ? date("Y-m-d") : $date;
        $dayNum = (int)date('w', strtotime($date));
        return $dayNum == 0;
    }

    public static function isSaturday($date = '')
    {
        $date = empty($date) ? date("Y-m-d") : $date;
        $dayNum = (int)date('w', strtotime($date));
        return $dayNum == 6;
    }

    public static function addIntervalInDate($date, $num = 1, $interval = 'days')
    {
        if ($num == 0) {
            return $date;
        }
        $num = $num < 0 ? "-{$num}" : "+{$num}";
        return date('Y-m-d', strtotime("{$num} {$interval}", strtotime($date)));
    }

    public static function addDaysInDate($date, $days = 1)
    {
        return self::addIntervalInDate($date, $days, 'days');
    }

    public static function subtractDaysFromDate($date, $days = 1)
    {
        return self::addIntervalInDate($date, $days * -1, 'days');
    }

    public static function getDatesFromRangeStr($rangeStr)
    {
        $dates = explode(',', $rangeStr);
        $startDate = @$dates[0];
        $endDate = @$dates[1];

        if (!empty($startDate) && !empty($endDate)) {
            $startDate = date("Y-m-d", strtotime($startDate));
            $endDate = date("Y-m-d", strtotime($endDate));
        }

        return [$startDate, $endDate];
    }

    public static function getConsecutiveDateRangesFromDatesList($dates)
    {
        if (count($dates) == 0) {
            return [];
        }
        sort($dates); // Ensure dates are sorted
        $result = [];
        $range_start = $range_end = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $current_date = $dates[$i];
            $previous_date = $dates[$i - 1];

            // Check if the current date is consecutive to the previous date
            if (strtotime($current_date) == strtotime($previous_date . ' +1 day')) {
                $range_end = $current_date;
            } else {
                // If not consecutive, push the current range to result
                $result[] = self::changeDateFormat($range_start, "Y-m-d", "Ymd") . ',' . self::changeDateFormat($range_end, "Y-m-d", "Ymd");
                $range_start = $range_end = $current_date;
            }
        }

        // Add the last range to the result
        $result[] = self::changeDateFormat($range_start, "Y-m-d", "Ymd") . ',' . self::changeDateFormat($range_end, "Y-m-d", "Ymd");

        return $result;
    }

}
