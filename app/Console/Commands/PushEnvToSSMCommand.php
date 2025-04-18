<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\Ssm\SsmClient;
use Illuminate\Support\Facades\File;

class PushEnvToSSMCommand extends Command
{
    protected $signature = 'push:env:ssm
        {--prefix= : SSM Prefix}
        {--env_file= : .env file name (e.g., .env.prod)}
    ';

    protected $description = 'Push environment variables to AWS SSM Parameter Store';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $paramPrefix = $this->option('prefix') ?: '';
        $envFileName = $this->option('env_file') ?: '.env';
        $envFilePath = base_path($envFileName);

        if (empty($paramPrefix)) {
            $this->error("SSM Param Prefix is empty.");
            return;
        }

        if (!File::exists($envFilePath)) {
            $this->error("Env file not found at: {$envFilePath}");
            return;
        }

        $this->info("Loading environment variables from {$envFilePath}");

        // ✅ Parse env file manually
        $envLines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envVariables = [];

        foreach ($envLines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $envVariables[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        // ✅ Initialize SSM client
        $ssmClient = new SsmClient([
            'region' => env("AWS_DEFAULT_REGION", 'us-east-1'),
            'version' => 'latest',
        ]);

        $this->info("Starting to push environment variables to SSM under prefix /{$paramPrefix}/");

        foreach ($envVariables as $key => $value) {
            try {
                $ssmClient->putParameter([
                    'Name' => "/{$paramPrefix}/{$key}",
                    'Value' => $value,
                    'Type' => 'String',
                    'Overwrite' => true,
                ]);

                $this->info("✅ Pushed {$key}={$value} to SSM.");
            } catch (\Exception $e) {
                $this->error("❌ Failed to push {$key}: " . $e->getMessage());
            }
        }

        $this->info("✅ All environment variables from {$envFileName} pushed to SSM.");
    }
}
