<?php

namespace App\TE\HelperClasses;

use Aws\S3\S3Client;
use Aws\Sts\StsClient;
use Illuminate\Support\Facades\Log;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\DirectoryAttributes;

class S3Helper
{
    public static $_REGION = 'us-east-1';
    public static $_VERSION = 'latest';

    /**
     * @param string $bucketName
     * @return string
     */
    public static function getBucketName($bucketName = '')
    {
        return empty($bucketName) ? env("AWS_BUCKET") : $bucketName;
    }

    /**
     * @param string $bucketName
     * @return Filesystem
     */
    public static function getFileSystem($bucketName = '')
    {
        $config = [
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'region' => env("AWS_DEFAULT_REGION", self::$_REGION),
            'version' => self::$_VERSION,
        ];

        $client = new S3Client($config);

        $adapter = new AwsS3V3Adapter($client, self::getBucketName($bucketName));
        $fileSystem = new Filesystem($adapter);

        return $fileSystem;
    }

    /**
     * @param $folderPath
     * @param string $bucketName
     * @return array
     */
    public static function getFilesList($folderPath, $bucketName = '')
    {
        $list = [];
        $filesystem = self::getFileSystem($bucketName);
        $files = null;
        $tries = 0;
        do {
            try {
                $files = $filesystem->listContents($folderPath);
            } catch (\Exception $exp) {
                Log::info("Error in getting s3 files list from [{$folderPath}] - [{$exp->getMessage()}]");
                $files = null;
                sleep(5);
            }
            $tries++;
        } while (is_null($files) && $tries <= 3);

        if (!is_null($files)) {
            foreach ($files as $object) {
                if ($object instanceof DirectoryAttributes) {
                    $list[] = $object->path();
                }
            }
        }

        return $list;
    }

    /**
     * @param $s3FilePath
     * @param string $bucketName
     * @return bool
     */
    public static function fileExistsOnS3($s3FilePath, $bucketName = '')
    {
        $filesystem = self::getFileSystem($bucketName);
        return $filesystem->has($s3FilePath);
    }

    /**
     * @param $localFilePath
     * @param $s3FilePath
     * @param string $bucketName
     **/
    public static function uploadFileToS3($localFilePath, $s3FilePath, $bucketName = '')
    {
        $uploaded = true;
        try {
            Log::info("Uploading to S3 {$localFilePath} -> " . self::getBucketName($bucketName) . "/{$s3FilePath}");
            $filesystem = self::getFileSystem($bucketName);
            $handler = fopen($localFilePath, 'r+');
            $filesystem->writeStream($s3FilePath, $handler);
            @fclose($handler);
        } catch (\Exception $e) {
            $uploaded = false;
            Log::error($e->getMessage());
            notifyBugsnagError($e, [
                'Error' => "File uploading to S3 failed",
                'local path' => $localFilePath,
                's3 path' => $s3FilePath
            ]);
        }
        return $uploaded;
    }

    /**
     * @param $s3FilePath
     * @param $localFilePath
     * @param string $bucketName
     */
    public static function downloadFileUsingFileSystem($s3FilePath, $localFilePath, $bucketName = '')
    {
        $filesystem = self::getFileSystem($bucketName);
//        dd($filesystem->has($s3FilePath));
        if ($filesystem->has($s3FilePath)) {
            $s3_file = $filesystem->read($s3FilePath);
            $file = fopen($localFilePath, 'w');
            fwrite($file, $s3_file->read());
            fclose($file);
        }
    }

    /**
     * @param $s3FilePath
     * @param $localFilePath
     * @param string $bucketName
     */
    public static function downloadFileUsingS3CP($s3FilePath, $localFilePath, $bucketName = '')
    {
        $s3CPCommand = "aws s3 cp ";
        $bucketName = self::getBucketName($bucketName);

        $s3CPCommand .= "s3://" . $bucketName . "/" . $s3FilePath . " " . $localFilePath;
        Log::info($s3CPCommand);
        exec($s3CPCommand);
    }

    /**
     * @param $s3FilePath
     * @param $localFilePath
     * @param string $bucketName
     */
    public static function copyFileUsingS3CP($sourceFilePath, $destinationFilePath, $bucketName = '', $args = '')
    {
        $s3CPCommand = "aws s3 cp ";
        $bucketName = self::getBucketName($bucketName);

        $s3CPCommand .= "s3://" . $bucketName . "/" . $sourceFilePath . " s3://" . $bucketName . "/" . $destinationFilePath . " $args";
        Log::info($s3CPCommand);

        try {
            exec($s3CPCommand);
            Log::info("File copied from source file : " . $sourceFilePath . "  ==>  " . $destinationFilePath . " on s3 successfully.");
        } catch (\Exception $e) {
            Log::info("Error while copying file to s3 => " . $e);
        }

    }


    /**
     * @param $s3FilePath
     * @param $localFilePath
     * @param string $bucketName
     */
    public static function moveFileUsingS3CP($sourceFilePath, $destinationFilePath, $bucketName = '', $args = '')
    {
        $bucketName = self::getBucketName($bucketName);

        $sourcePrefix = "s3://" . $bucketName . "/" . $sourceFilePath;
        $destinationPrefix = "s3://" . $bucketName . "/" . $destinationFilePath;

        $s3CPCommand = "aws s3 mv {$sourcePrefix} {$destinationPrefix} {$args}";
        Log::info($s3CPCommand);
        exec($s3CPCommand);
    }

    public static function getSTSClient($sts_key = '', $sts_secret = '')
    {
        $config = [
            'credentials' => [
                'key' => !empty($sts_key) ? $sts_key : env('SP_API_ACCESS_KEY', ''),
                'secret' => !empty($sts_secret) ? $sts_secret : env('SP_API_SECRET_KEY', ''),
            ],
            'region' => self::$_REGION,
            'version' => self::$_VERSION,
        ];
        $client = new StsClient($config);
        return $client;
    }

    public static function generateAWSSessionToken($sts_key = '', $sts_secret = '', $sts_arn = '')
    {
        $client = self::getSTSClient($sts_key, $sts_secret);
        $sts_arn = empty($sts_arn) ? env("SP_API_ROLE_ARN") : $sts_arn;
        $args = ['RoleArn' => $sts_arn, 'RoleSessionName' => env("SP_API_STS_NAME", 'AWSCLI-Session'), "DurationSeconds" => env("SP_STS_DURATION", 3600)];

        $resp = $client->assumeRole($args);
        return $resp;
    }
}
