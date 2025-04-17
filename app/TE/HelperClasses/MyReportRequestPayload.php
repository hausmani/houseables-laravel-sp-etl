<?php

namespace App\TE\HelperClasses;

use SellingPartnerApi\Seller\ReportsV20210630\Dto\CreateReportSpecification;

class MyReportRequestPayload
{
    private $profile_type;
    private $marketplaceId;
    private $reportType;
    private $startDate;
    private $endDate;

    private $reportOptions;

    public function __construct($profile_type, $marketplaceId, $reportType, $startDate, $endDate)
    {
        $this->profile_type = $profile_type;
        $this->marketplaceId = $marketplaceId;
        $this->reportType = $reportType;
        $this->startDate = DateHelper::formatDateISO8601($startDate);
        $this->endDate = DateHelper::formatDateISO8601($endDate, true);
        $this->reportOptions = null;
    }

    public function getPayload(): CreateReportSpecification
    {
        $reportType = ETLHelper::allReportsConfiguration($this->profile_type, $this->reportType, 'reportType');
        $payloadConfig = ETLHelper::allReportsConfiguration($this->profile_type, $this->reportType, 'payload');

        $reportOptions = null;
        if (!empty($payloadConfig['reportOptions'])) {
            $reportOptions = $payloadConfig['reportOptions'];
        }

        $startDate = null;
        $endDate = null;
        if ($payloadConfig['setDates']) {
            $startDate = new \DateTime($this->startDate);
            $endDate = new \DateTime($this->endDate);
        }

        $this->reportOptions = $reportOptions;

        $reportSpecification = new CreateReportSpecification(
            $reportType,
            [$this->marketplaceId],
            $reportOptions,
            $startDate,
            $endDate
        );

        return $reportSpecification;
    }

    public function getReportOptions()
    {
        return $this->reportOptions;
    }

}
