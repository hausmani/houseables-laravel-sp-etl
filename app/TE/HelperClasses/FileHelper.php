<?php


namespace App\TE\HelperClasses;


use Illuminate\Support\Facades\Log;

class FileHelper
{
    public static function downloadZipFileFromAmazonApi($fileDownloadURL, $downloadedFilePath, $compressionAlgo)
    {
        $chunkSize = 1024 * 8;
        $code = '';

        $alreadyCompressed = te_compare_strings($compressionAlgo, 'GZIP');

        try {

            $file = fopen($fileDownloadURL, "rb");
            if ($file) {
                if (!file_exists($downloadedFilePath)) {

                    $dst_file = $alreadyCompressed ? fopen($downloadedFilePath, "w5") : gzopen($downloadedFilePath, "w5");
                    while (!feof($file)) {
                        $chunk = fread($file, $chunkSize);
                        if ($alreadyCompressed) {
                            fwrite($dst_file, $chunk);
                        } else {
                            gzwrite($dst_file, $chunk);
                        }
                    }
                    fclose($file);
                    if ($alreadyCompressed) {
                        fclose($dst_file);
                    } else {
                        gzclose($dst_file);
                    }
                    return true;
                } else {
                    error_log("$downloadedFilePath already exists");
                }
            } else {
                error_log("$fileDownloadURL does not exists");
            }
            return false;

        } catch (\Exception $e) {
            Log::info("Error occurred while downloading " . $downloadedFilePath . " " . $e->getMessage());
            $httpStatusStr = @$http_response_header[0];
            $httpStatusStr = trim($httpStatusStr);
            if (stripos($httpStatusStr, 'HTTP/') !== false) {
                $exploded = explode(' ', $httpStatusStr, 3);
                $code = @$exploded[1];
            }
            return $code;
        }
    }

    public static function getCSVFileHeader($csvFilePath, $delimiter = "\t")
    {
        $headerRow = [];

        try {
            $file = fopen($csvFilePath, "r");
            while (!feof($file)) {
                $headerRow = fgetcsv($file);
                break;
            }
        } catch (\Exception $e) {
            $headerRow = [];
            notifyBugsnagError($e, ['csv file path' => $csvFilePath], 'info');
        } finally {
            @fclose($file);
        }

        return $headerRow;
    }
}
