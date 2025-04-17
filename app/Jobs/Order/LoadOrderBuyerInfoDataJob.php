<?php

namespace App\Jobs\Order;

use App\Jobs\Job;
use App\TE\HelperClasses\BQHelper;
use App\TE\HelperClasses\MyRedis;

class LoadOrderBuyerInfoDataJob extends Job
{
    public $redis_key;
    public $client_id;
    public $marketplaceId;
    public $sellerId;
    public $gcs_uris;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($redis_key)
    {
        $this->redis_key = $redis_key;

        $this->onQueue(Q_SELLER_ORDER_BUYERINFO_LOAD_BQ);
        $this->switchToTestQueueIfTestServer();
    }

    public function handle()
    {
        $this->extract_values_from_redis_key();
        $this->gcs_uris = MyRedis::redis_fetch_set_values($this->redis_key);

        if (count($this->gcs_uris) == 0) {
            return;
        }

        $uri_chunks = createChunksOfData($this->gcs_uris, 2500);

        foreach ($uri_chunks as $gcs_uris) {
            $this->loadDataInBQ($gcs_uris);
            MyRedis::redis_remove_set_elements($this->redis_key, $gcs_uris);
        }
    }

    private function extract_values_from_redis_key()
    {
        $exploded = explode("__", $this->redis_key);

        $ids = @$exploded[1];
        $ids_exploded = explode("_", $ids);
        $this->client_id = @$ids_exploded[0];
        $this->marketplaceId = @$ids_exploded[1];
        $this->sellerId = @$ids_exploded[2];
    }

    private function getTableSchema()
    {
        return [
            'fields' => [
                ['name' => "marketplace", 'type' => "STRING"],
                ['name' => "sellerId", 'type' => "STRING"],
                ['name' => "purchase_date", 'type' => "TIMESTAMP"],
                ['name' => "last_updated", 'type' => "TIMESTAMP"],
                ['name' => "AmazonOrderId", 'type' => "STRING"],
                ['name' => "BuyerEmail", 'type' => "STRING"],
                ['name' => "BuyerName", 'type' => "STRING"],
                ['name' => "BuyerCounty", 'type' => "STRING"],
                ['name' => "BuyerTaxInfo", 'type' => "STRING"],
                ['name' => "PurchaseOrderNumber", 'type' => "STRING"],
            ]
        ];
    }

    private function loadDataInBQ($gcs_uris)
    {
        $tempTableId = $this->getTempTableName();
        $created = BQHelper::createTable($tempTableId, $this->getDatasetId(), $this->getTableSchema());
        if ($created) {
            $dataLoaded = BQHelper::loadParquetFilesInTable($tempTableId, $this->getDatasetId(), $this->getTableSchema(), $gcs_uris);
            if ($dataLoaded) {
                $this->mergeTables($tempTableId);
            }
        }
    }

    private function mergeTables($tempTableId)
    {
        $projectId = $this->getProjectId();
        $datasetId = $this->getDatasetId();
        $tableId = $this->getBQTableName();

        $temp_table_selection_query = "SELECT DISTINCT *  FROM `{$projectId}.{$datasetId}.{$tempTableId}`";

        $where_conditions = [];
        foreach (['marketplace', 'sellerId', 'purchase_date', 'AmazonOrderId'] as $column) {
            $where_conditions[] = "source_table.{$column} = target_table.$column";
        }
        $merge_conditions = implode(" AND ", $where_conditions);
        $delete_from_source_clause = '';

        $schema = $this->getTableSchema();
        $update_conditions = [];
        foreach ($schema['fields'] as $schemaColumn) {
            $col = $schemaColumn['name'];
            $update_conditions[] = "{$col} = source_table.{$col}";
        }

        $update_conditions = implode(" , ", $update_conditions);
        $query = "MERGE
                {$projectId}.{$datasetId}.{$tableId} target_table using ( {$temp_table_selection_query} ) source_table
                ON {$merge_conditions}
                WHEN NOT MATCHED BY TARGET THEN INSERT ROW
                {$delete_from_source_clause}
                WHEN MATCHED THEN UPDATE SET {$update_conditions}
                ";

        list($merged, $queryResult) = BQHelper::runQuery($query);
        return $merged;
    }

    private function getProjectId()
    {
        return env('GOOGLE_PROJECT_ID', 'amazon-sp-report-loader');
    }

    private function getDatasetId()
    {
        return is_local_environment() ? 'staging' : 'orders';
    }

    private function getTempTableName()
    {
        return "tmp_" . date("Ymd") . "_OBI_{$this->client_id}_{$this->sellerId}_{$this->marketplaceId}_" . time() . '_' . rand(0, 99999);
    }

    private function getBQTableName()
    {
        return "orders_buyer_info_{$this->client_id}";
    }

}
