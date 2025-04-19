<?php

namespace App\TE\HelperClasses;

use App\Models\ClientProfile;
use App\Models\ClientAuthorisation;
use Illuminate\Support\Facades\Log;
use SellingPartnerApi\SellingPartnerApi;

class SpApiHelper
{

    public static function apiConnector($refresh_token, $marketplaceId)
    {

        return SellingPartnerApi::seller(
            config('services.selling_partner.lwa_app_id'),
            config('services.selling_partner.lwa_client_secret'),
            $refresh_token,
            self::getByMarketplaceId($marketplaceId)
        );

    }

    public static function getReportsApiClient($profile_id, $auth_id, $marketplaceId)
    {
        $apiClient = false;
        $auth = ClientAuthorisation::where("id", $auth_id)
            ->where('active', 1)
            ->first();
        if ($auth) {
            $auth = $auth->toArray();
            Log::info("Getting auth to perform ReportsApi calls using channel id => {$profile_id}");
            try {

                return self::apiConnector($auth['refresh_token'], $marketplaceId)->reportsV20210630();

            } catch (\Exception $e) {
                if ($e->getCode() == 400) {

                    Log::info("ERROR : Unauthorized Profile {$profile_id} - [{$e->getCode()}] - [{$e->getMessage()}]");

                } else {
                    Log::info($e);
                    notifyBugsnagError($e, [
                        'code' => $e->getCode(),
                        'Error' => $e,
                    ]);
                }
                sleep(1);
            }
        }

        return $apiClient;
    }

    public static function getAWDApiClient($profile_id, $auth_id, $marketplaceId)
    {
        $apiClient = false;
        $auth = ClientAuthorisation::where("id", $auth_id)
            ->where('active', 1)
            ->first();
        if ($auth) {
            $auth = $auth->toArray();
            Log::info("Getting auth to perform AWD API calls using channel id => {$profile_id}");
            try {

                return self::apiConnector($auth['refresh_token'], $marketplaceId)->amazonWarehousingAndDistributionV20240509();

            } catch (\Exception $e) {
                if ($e->getCode() == 400) {

                    Log::info("ERROR : Unauthorized Profile {$profile_id} - [{$e->getCode()}] - [{$e->getMessage()}]");

                } else {
                    Log::info($e);
                    notifyBugsnagError($e, [
                        'code' => $e->getCode(),
                        'Error' => $e,
                    ]);
                }
                sleep(1);
            }
        }

        return $apiClient;
    }

    public static function getFBAInboundV0ApiClient($profile_id, $auth_id, $marketplaceId)
    {
        $apiClient = false;
        $auth = ClientAuthorisation::where("id", $auth_id)
            ->where('active', 1)
            ->first();
        if ($auth) {
            $auth = $auth->toArray();
            Log::info("Getting auth to perform FBA Inbound V0 calls using channel id => {$profile_id}");
            try {

                $connector = self::apiConnector($auth['refresh_token'], $marketplaceId)->fbaInboundV0();
                return $connector;

            } catch (\Exception $e) {
                if ($e->getCode() == 400) {

                    Log::info("ERROR : Unauthorized Profile {$profile_id} - [{$e->getCode()}] - [{$e->getMessage()}]");

                } else {
                    Log::info($e);
                    notifyBugsnagError($e, [
                        'code' => $e->getCode(),
                        'Error' => $e,
                    ]);
                }
                sleep(1);
            }
        }

        return $apiClient;
    }

    public static function getOrdersV0ApiClient($profile_id, $auth_id, $marketplaceId)
    {
        $apiClient = false;
        $auth = ClientAuthorisation::where("id", $auth_id)
            ->where('active', 1)
            ->first();
        if ($auth) {
            $auth = $auth->toArray();
            Log::info("Getting auth to perform Orders V0 calls using channel id => {$profile_id}");
            try {

                return self::apiConnector($auth['refresh_token'], $marketplaceId)->ordersV0();

            } catch (\Exception $e) {
                if ($e->getCode() == 400) {

                    Log::info("ERROR : Unauthorized Profile {$profile_id} - [{$e->getCode()}] - [{$e->getMessage()}]");

                } else {
                    Log::info($e);
                    notifyBugsnagError($e, [
                        'code' => $e->getCode(),
                        'Error' => $e,
                    ]);
                }
                sleep(1);
            }
        }

        return $apiClient;
    }

    public static function getNotificationsApiClient($profile)
    {
        $apiClient = false;

        if ($profile) {

            $auth = ClientAuthorisation::where("id", $profile->client_authorisation_id)
                ->first();
            if ($auth) {
                $auth = $auth->toArray();
                Log::info("\n Getting auth to perform Notification Api calls using channel id => {$profile->id} \n");
                try {

                    return self::apiConnector($auth['refresh_token'], $profile->marketplaceId)->notificationsV1();

                } catch (\Exception $e) {

                    if ($e->getCode() == 400) {

                        Log::info("ERROR : Unauthorized channel {$profile->id}");

                    } else {
                        $apiClient = false;
                        Log::info($e);
                        notifyBugsnagError($e, [
                            'code' => $e->getCode(),
                            'Error' => $e,
                        ]);
                    }
                    sleep(1);
                }
            }
        }

        return $apiClient;
    }

    public static function createSubscription($profile_id)
    {
        $apiClient = false;
        $profile = ClientProfile::where("id", $profile_id)->first();

        if ($profile) {
            $apiClient = self::getNotificationsApiClient($profile);
            if ($apiClient) {

                $destinationId = self::getDestinationId($profile->marketplaceId);

                $req = new CreateSubscriptionRequest([
                    'payload_version' => '1.0',
                    'destination_id' => $destinationId
                ]);
                $notificationType = "REPORT_PROCESSING_FINISHED";
                try {
                    $response = $apiClient->createSubscription($notificationType, $req);
                } catch (ApiException $apiException) {
                    notifyBugsnagError($apiException, [
                        "profile id" => $profile_id
                    ]);
                }
            }
        }
    }

    public static function cancelSubscription($profile_id)
    {
        $apiClient = false;
        $profile = ClientProfile::where("id", $profile_id)->first();

        if ($profile) {
            $apiClient = self::getNotificationsApiClient($profile);
            if ($apiClient) {

                $notificationType = "REPORT_PROCESSING_FINISHED";
                try {
                    $response = $apiClient->getSubscription($notificationType);
                    $subId = $response->getPayload()->getSubscriptionId();
                    $apiClient->deleteSubscriptionById($subId);
                } catch (ApiException $apiException) {
                    notifyBugsnagError($apiException, [
                        "profile id" => $profile_id
                    ]);
                }
            }
        }
    }

    public static function getDestinationId($marketplaceId)
    {
        $region = getMarketplaceInfo('marketplaceId', $marketplaceId, 'region');
        $region = strtoupper($region);
        $destinationIds = [
            "NA" => 'ba5c4943-e8a5-4c78-847e-256bc803c7d3',
            "EU" => 'aedeb2b2-7a74-4b54-9876-316e4468a613',
            "FE" => 'cf480691-89d2-493a-b215-21ce68b7301b'
        ];
        return empty($destinationIds[$region]) ? '' : $destinationIds[$region];
    }

    public static function getByMarketplaceId(string $marketplace_id, bool $sandbox = false)
    {
        $map = [
            // North America.
            // Brazil.
            'A2Q3Y263D00KWC' => 'NA',
            // Canada
            'A2EUQ1WTGCTBG2' => 'NA',
            // Mexico.
            'A1AM78C64UM0Y8' => 'NA',
            // US.
            'ATVPDKIKX0DER' => 'NA',
            // Europe.
            // United Arab Emirates (U.A.E.).
            'A2VIGQ35RCS4UG' => 'EU',
            // Belgium.
            'AMEN7PMS3EDWL' => 'EU',
            // Germany.
            'A1PA6795UKMFR9' => 'EU',
            // Egypt.
            'ARBP9OOSHTCHU' => 'EU',
            // Spain.
            'A1RKKUPIHCS9HS' => 'EU',
            // France.
            'A13V1IB3VIYZZH' => 'EU',
            // UK.
            'A1F83G8C2ARO7P' => 'EU',
            // India.
            'A21TJRUUN4KGV' => 'EU',
            // Italy.
            'APJ6JRA9NG5V4' => 'EU',
            // Netherlands.
            'A1805IZSGTT6HS' => 'EU',
            // Poland.
            'A1C3SOZRARQ6R3' => 'EU',
            // Saudi Arabia.
            'A17E79C6D8DWNP' => 'EU',
            // Sweden.
            'A2NODRKZP88ZB9' => 'EU',
            // Turkey.
            'A33AVAJ2PDY3EV' => 'EU',
            // Far East.
            // Singapore.
            'A19VAU5U5O7RUS' => 'FE',
            // Australia.
            'A39IBJ37TRP1C6' => 'FE',
            // Japan.
            'A1VC38T7YXB528' => 'FE',
        ];
        if (!isset($map[$marketplace_id])) {
            throw new \Exception(sprintf(
                'Unknown marketplace ID "%s".',
                $marketplace_id
            ));
        }

        $region = $map[$marketplace_id];
        if ($sandbox) {
            $region .= '_SANDBOX';
        }
        return constant("SellingPartnerApi\Enums\Endpoint::$region");
    }

}
