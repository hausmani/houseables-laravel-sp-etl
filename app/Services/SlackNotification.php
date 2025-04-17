<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SlackNotification
{
    protected $client;
    protected $webhookUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    public function send($blocks)
    {
        $payload = [
            'blocks' => $blocks,
        ];

        try {
            $response = $this->client->post($this->webhookUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            throw new \Exception('Failed to send Slack notification: ' . $e->getMessage());
        }
    }
}
