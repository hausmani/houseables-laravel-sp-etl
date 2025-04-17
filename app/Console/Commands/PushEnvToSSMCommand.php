<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\Ssm\SsmClient;
use Illuminate\Support\Facades\File;

class PushEnvToSSMCommand extends Command
{
    protected $signature = 'push:env:ssm
        {--prefix= : SSM Prefix}
        {--env_file= : .env file name}
    ';
    protected $description = 'Push environment variables to AWS SSM Parameter Store';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $paramPrefix = $this->option('prefix') ? $this->option('prefix') : '';
        $env_file = $this->option('env_file') ? $this->option('env_file') : '.env';

        if (empty($paramPrefix)) {
            $this->error("SSM Param Prefix is empty.");
            return;
        }

        $envFilePath = base_path($env_file);

        // Load the environment variables
        if (!File::exists($envFilePath)) {
            $this->error("Env file not found at: {$envFilePath}");
            return;
        }

        $this->info("Loading environment variables from {$envFilePath}");
        $envVariables = getenv(); // Returns all environment variables

        // Create SSM client
        $ssmClient = new SsmClient([
            'region' => env("AWS_DEFAULT_REGION", 'us-east-1'),
            'version' => 'latest',
        ]);

        $write = false;

        foreach ($envVariables as $key => $value) {

            $value = empty($value) ? '' : $value;

            if ($write) {

                if (empty($value)) {
                    // Delete the parameter from SSM if value is empty
                    try {
                        $ssmClient->deleteParameter([
                            'Name' => "/{$paramPrefix}/{$key}",
                        ]);

                        $this->info("Successfully deleted {$key} from SSM Parameter Store.");
                    } catch (\Exception $e) {
                        $this->error("Failed to delete {$key} from SSM: " . $e->getMessage());
                    }
                } else {

                    $paramDetail = [
                        'Name' => "/{$paramPrefix}/{$key}",
                        'Value' => (string)$value,
                        'Type' => 'String',
                        'Description' => (string)$key,
                    ];

                    try {
                        $result = $ssmClient->putParameter([
                            'Name' => $paramDetail['Name'],
                            'Value' => $paramDetail['Value'],
                            'Type' => $paramDetail['Type'],
                            'Description' => $paramDetail['Description'],
                            'Overwrite' => true,
                        ]);

                        $this->info("Successfully pushed {$key} to SSM Parameter Store.");
                    } catch (\Exception $e) {
                        $this->error("Failed to push {$key} to SSM: " . $e->getMessage());
                    }
                }
            } else {
                $this->line("Skipping --> {$key} = {$value}");
            }

            $write = $key == '_' || $write ? true : false;
        }

        $this->info("Environment variables push process completed.");
    }
}
