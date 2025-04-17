<?php

namespace App\TE;

use \Aws\Sqs\SqsClient;

class QuerySQS
{
    private static $sqsClient = null;
    private static $sqsConfig = [];

    private static function getAthenaObj()
    {
        if (!self::$sqsClient) {

            $options = [
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY', 'test')
                ]
            ];
            self::$sqsClient = new SqsClient($options);
            self::$sqsConfig['QueueUrl'] = env('AWS_SQS_PREFIX', '');
        }
        return self::$sqsClient;

    }

    /*
     * @param $data array
     * @param $queueName string name of the queue
     * @return array
     * addMessage function to add message in sqs
     */
    public static function sendMessage($data, $queueName)
    {
        $sqsClient = self::getAthenaObj();
        $params = [
            'MessageBody' => json_encode($data),
            'QueueUrl' => self::$sqsConfig['QueueUrl'] . '/' . $queueName
        ];
        return $sqsClient->sendMessage($params);
    }

    /*
     * @param $queueName string name of the queue
     * @return mix
     * get detail of the sqs
     */
    public static function getDetail($queueName)
    {
        $sqsClient = self::getAthenaObj();
        $params = [
            'QueueUrl' => self::$sqsConfig['QueueUrl'] . '/' . $queueName,
            'AttributeNames' => [
                "ApproximateNumberOfMessages",
                "ApproximateNumberOfMessagesDelayed",
                "ApproximateNumberOfMessagesNotVisible"
            ]
        ];
        try {
            return $sqsClient->getQueueAttributes($params)->toArray()['Attributes'];
        } catch (\Exception $e) {
            return null;
        }
    }

}
