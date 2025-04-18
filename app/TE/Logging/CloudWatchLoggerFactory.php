<?php

namespace App\TE\Logging;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Logger;

class CloudWatchLoggerFactory
{
    /**
     * Create a custom Monolog instance.
     *
     * @param array $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $sdkParams = $config["sdk"];
        $tags = $config["tags"] ?? [];
        $name = $config["name"] ?? 'cloudwatch';

        // Instantiate AWS SDK CloudWatch Logs Client
        $client = new CloudWatchLogsClient($sdkParams);

        // Log group name, will be created if none
        $groupName = $config['group'];

        // Log stream name, will be created if none
        $streamName = $config['stream'];

        // Days to keep logs, 14 by default. Set to `null` to allow indefinite retention.
        $retentionDays = $config["retention"];

        // Instantiate handler (tags are optional)
        $handler = new CloudWatch($client, $groupName, $streamName, $retentionDays, 0, $tags);

        // Create a log channel
        $logger = new Logger($name);
        // Set handler
        $logger->pushHandler($handler);

        return $logger;
    }
}
