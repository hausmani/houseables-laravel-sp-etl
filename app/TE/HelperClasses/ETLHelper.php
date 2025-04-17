<?php

namespace App\TE\HelperClasses;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ETLHelper
{
    public static function allReportsConfiguration($profileType, $reportType = '', $attr = '')
    {
        $reportsConfig = [
            PROFILE_SELLER_CENTRAL => [
                FBA_MYI_UNSUPPRESSED_INVENTORY_DATA => [
                    "ext" => "tsv",
                    "sla" => "today",
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "1 Day",
                    ],
                ],
                RESERVED_INVENTORY_DATA => [
                    "ext" => "tsv",
                    "sla" => "today",
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "1 Day",
                    ],
                ],
//                AFN_INVENTORY_DATA => [
//                    "ext" => "tsv",
//                    "sla" => "today",
//                    "pattern" => [
//                        "new" => "0 Day",
//                        "restatement" => "1 Day",
//                    ],
//                ],
                MERCHANT_LISTINGS_ALL_DATA => [
                    "ext" => "tsv",
                    "sla" => "today",
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "1 Day",
                    ],
                ],
                SALES_AND_TRAFFIC_REPORT => [
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "30 Days",
                        "historical" => "180 Days",
                        "report_range" => "30 Day"
                    ],
                    "sla" => 1,
                    "payload" => [
                        "setDates" => true,
                        "reportOptions" => [
                            "asinGranularity" => "CHILD",
                            "dateGranularity" => "DAY"
                        ]
                    ],
                ],
                FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_GENERAL => [
                    "ext" => "tsv",
                    "sla" => 0,
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "60 Days",
                        "historical" => "6 Months",
                        "report_range" => "15 Days"
                    ],
                    "payload" => [
                        "setDates" => true,
                    ]
                ],
                FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA => [
                    "ext" => "tsv",
                    "sla" => 0,
                    "pattern" => [
                        "new" => "0 Day",
                        "restatement" => "60 Days",
                        "historical" => "6 Months",
                        "report_range" => "15 Days"
                    ],
                    "payload" => [
                        "setDates" => true,
                    ]
                ],
//                AMAZON_FULFILLED_SHIPMENTS_DATA_GENERAL => [
//                    "ext" => "tsv",
//                    "sla" => 1,
//                    "pattern" => [
//                        "new" => "0 Day",
//                        "restatement" => "30 Day",
//                        "historical" => "6 Months",
//                        "report_range" => "15 Days"
//                    ],
//                    "payload" => [
//                        "setDates" => true,
//                    ]
//                ],
//                FLAT_FILE_RETURNS_DATA_BY_RETURN_DATE => [
//                    "ext" => "tsv",
//                    "sla" => 1,
//                    "pattern" => [
//                        "new" => "0 Day",
//                        "restatement" => "30 Day",
//                        "historical" => "6 Months",
//                        "report_range" => "30 Days"
//                    ],
//                    "payload" => [
//                        "setDates" => true,
//                    ]
//                ],
            ],
            PROFILE_VENDOR_CENTRAL => [

            ]
        ];

        $profileType = getProfileType($profileType);

        if (isset($reportsConfig[$profileType])) {
            $channelReportsConfig = $reportsConfig[$profileType];

            if (empty($reportType)) {
                // if report type is not given, then we will return the report names array for that channel type
                $report_types = array_keys($channelReportsConfig);
                $auto_reports = ETLHelper::getAutoCreatedReportTypesToDownload($profileType);
                return array_diff($report_types, $auto_reports);

            } else {
                $reportTypeConfig = empty($channelReportsConfig[$reportType]) ? [] : $channelReportsConfig[$reportType];
                if (empty($attr)) {
                    return $reportTypeConfig;
                } else {
                    $reportTypeConfig[$attr] = !isset($reportTypeConfig[$attr]) ? '' : $reportTypeConfig[$attr];
                    if (empty($reportTypeConfig[$attr]) && $reportTypeConfig[$attr] !== 0) {
                        // attr not set in config, we will return default
                        $default = [
                            "reportType" => $reportType,
                            "creation" => "manual",
                            "ext" => "json",
                            "pattern" => [
                                "new" => "1 Day",
                                "restatement" => "1 Day",
                                "historical" => "1 Day",
                                "report_range" => "1 Day"
                            ],
                            "sla" => 2,
                            "granularity" => "DAY",
                            "payload" => [
                                "setDates" => false,
                                "reportOptions" => []
                            ]
                        ];
                        return @$default[$attr];
                    } else {
                        $config = $reportTypeConfig[$attr];
                        if ($attr == 'payload') {
                            if (!isset($config['reportOptions'])) {
                                $config['reportOptions'] = [];
                            }
                            if (!isset($config['setDates'])) {
                                $config['setDates'] = false;
                            }
                        } else if ($attr == 'pattern') {
                            if (!isset($config['new'])) {
                                $config['new'] = '1 Day';
                            }
                            if (!isset($config['restatement'])) {
                                $config['restatement'] = '1 Day';
                            }
                            if (!isset($config['historical'])) {
                                $config['historical'] = '1 Day';
                            }
                            if (!isset($config['report_range'])) {
                                $config['report_range'] = '1 Day';
                            }
                        }
                        return $config;
                    }
                }
            }

        } else {
            return [];
        }
    }

    public static function getAutoCreatedReportTypesToDownload($profileType)
    {
        $reportTypes = [
            PROFILE_SELLER_CENTRAL => [
                V2_SETTLEMENT_REPORT_DATA_FLAT_FILE_V2
            ],
            PROFILE_VENDOR_CENTRAL => []
        ];
        $profileType = getProfileType($profileType);
        $reportTypes = isset($reportTypes[$profileType]) ? $reportTypes[$profileType] : [];
        return $reportTypes;
    }

    public static function getOutsiteRequestedReportsToDownload()
    {
        $reportTypes = [
            AFN_INVENTORY_DATA,
            FBA_MYI_UNSUPPRESSED_INVENTORY_DATA,
            RESERVED_INVENTORY_DATA,
            MERCHANT_LISTINGS_ALL_DATA,
//            FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_GENERAL,
            FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA,
        ];
        return $reportTypes;
    }

    public static function getFileExtension($profileType, $reportType)
    {
        return self::allReportsConfiguration($profileType, $reportType, "ext");
    }

    public static function getApiPatterns($profileType, $reportType, $backfill, $customDateRange, $reportRange, $dateRangeOrder = 'DESC')
    {
        $backfill = empty($backfill) ? "restatement" : $backfill;
        $customDateRange = empty($customDateRange) ? "" : $customDateRange;
        $reportRange = empty($reportRange) ? "" : $reportRange;

        if (iCheckInArray($reportType, [AWD_INBOUND_SHIPMENT]) != -1) {

            $pattern = [
                "new" => "0 Day",
                "restatement" => "7 Days",
                "historical" => "90 Days",
                "report_range" => "15 Days"
            ];
            $sla = 1;
            $granularity = "DAY";
            $step = empty($reportRange) ? $pattern['report_range'] : $reportRange;

        } else if (iCheckInArray($reportType, [FBA_INBOUND_SHIPMENT]) != -1) {

            $pattern = [
                "new" => "0 Day",
                "restatement" => "3 Days",
                "historical" => "2 Years",
                "report_range" => "30 Days"
            ];
            $sla = 0;
            $granularity = "DAY";
            $step = empty($reportRange) ? $pattern['report_range'] : $reportRange;

        } else if (iCheckInArray($reportType, [FBA_INBOUND_SHIPMENT_ITEM]) != -1) {

            $pattern = [
                "new" => "0 Day",
                "restatement" => "3 Days",
                "historical" => "2 Years",
                "report_range" => "30 Days"
            ];

            $sla = 0;
            $granularity = "DAY";
            $step = empty($reportRange) ? $pattern['report_range'] : $reportRange;

        } else {
            $pattern = self::allReportsConfiguration($profileType, $reportType, "pattern");
            $sla = self::allReportsConfiguration($profileType, $reportType, "sla");
            $granularity = self::allReportsConfiguration($profileType, $reportType, "granularity");
            $step = empty($reportRange) ? $pattern['report_range'] : $reportRange;
        }

        if (te_compare_strings($granularity, "WEEK")) {
            // end date should be the last saturday if today is Monday or onwards.
            $endDate = DateHelper::getLastSaturday();
        } else {
            if (te_compare_strings($sla, "sunday")) {
                $endDate = DateHelper::getRecentSunday();
            } else if (te_compare_strings($sla, "last_sunday")) {
                $endDate = DateHelper::getRecentSunday();
                $endDate = DateHelper::subtractDaysFromDate($endDate, 7);
            } else if (te_compare_strings($sla, "today")) {
                $endDate = Carbon::today()->toDateString();
            } else {
                $endDate = Carbon::today()->subDays($sla)->toDateString();
            }
        }

        if (iCheckInArray($sla, ['today']) != -1) {
            $startDate = $endDate;

            if (iCheckInArray($backfill, ['new', 'restatement', 'historical']) !== -1) {
                list($durationValue, $durationUnit) = DateHelper::getDurationValue($pattern[$backfill]);
                if (empty($durationValue)) {
                    return [];
                }
            }

        } else {

            if (te_compare_strings($backfill, 'custom') || te_compare_strings($backfill, 'smart')) {

                list($startDate, $endDate) = DateHelper::getDatesFromRangeStr($customDateRange);

                if (te_compare_strings($sla, "sunday") || te_compare_strings($sla, "last_sunday")) {
                    $startDate = DateHelper::getRecentSunday($startDate);
                    $endDate = DateHelper::getRecentSunday($endDate);
                } else {

                    if (te_compare_strings($granularity, "WEEK")) {
                        $startDate = DateHelper::getRecentSunday($startDate);
                        $endDate = DateHelper::isSaturday($startDate) ? $endDate : DateHelper::getLastSaturday($endDate);
                    }
                }

            } else if (te_compare_strings($backfill, 'new')) {

                list($durationValue, $durationUnit) = DateHelper::getDurationValue($pattern['new']);
                if (empty($durationValue)) {
                    return [];
                }

                $startDate = DateHelper::getDateByDuration($endDate, $pattern['new']);

            } else if (te_compare_strings($backfill, 'restatement')) {

                list($durationValue, $durationUnit) = DateHelper::getDurationValue($pattern['restatement']);
                if (empty($durationValue)) {
                    return [];
                }

                $endDate = DateHelper::getDateByDuration($endDate, $pattern['new']);
                $endDate = DateHelper::getDateByDuration($endDate, '2 Days');
                $startDate = DateHelper::getDateByDuration($endDate, $pattern['restatement']);

            } else if (te_compare_strings($backfill, 'historical')) {

                list($durationValue, $durationUnit) = DateHelper::getDurationValue($pattern['historical']);
                if (empty($durationValue)) {
                    return [];
                }

                $startDate = DateHelper::getDateByDuration($endDate, $pattern['historical']);

            } else {

                $startDate = DateHelper::getDateByDuration($endDate, $backfill);

            }

        }

        $dateRanges = DateHelper::getDateRangesBetweenTwoDates($startDate, $endDate, $step, 'Y-m-d', $sla);
        if (te_compare_strings($dateRangeOrder, 'ASC')) {
            $dateRanges = array_reverse($dateRanges);
        }

        return $dateRanges;

    }

}
