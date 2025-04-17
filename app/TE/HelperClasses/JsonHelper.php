<?php


namespace App\TE\HelperClasses;

class JsonHelper
{
    public static function writeJsonFile($jsonFileNamePath, $jsonData)
    {
        try {
            if (file_exists($jsonFileNamePath)) {
                //if we get empty json and file is already has json then we can't write empty json in the file
                if ($jsonData == '[]') {
                    return;
                }
                preg_match('/^\[(.+)\]$/', $jsonData, $removeBracket);
                $jsonFileHandle = fopen($jsonFileNamePath, 'c');
                // move back a byte
                fseek($jsonFileHandle, -1, SEEK_END);

                $pointerPosition = ftell($jsonFileHandle);
                if ($pointerPosition > 0) {
                    // add the new json string
                    if (count($removeBracket) > 1) {
                        fwrite($jsonFileHandle, ',' . $removeBracket[1] . ']');
                    } else {
                        notifyBugsnagError('writeJsonFile function bracker error', [
                            'error message' => 'removeBracket index issue',
                            'temp json file name' => $jsonFileNamePath,
                            'json data' => $jsonData,
                            'removeBracket' => @$removeBracket,
                        ]);
                    }
                } else {
                    fwrite($jsonFileHandle, $jsonData);
                }
            } else {
                $jsonFileHandle = fopen($jsonFileNamePath, 'w');
                // write the first event inside an array
                fwrite($jsonFileHandle, $jsonData);
            }
            fclose($jsonFileHandle);

        } catch (\Exception $e) {
            notifyBugsnagError($e, [
                'error message' => $e->getMessage(),
                'temp json file name' => $jsonFileNamePath,
                'json data' => $jsonData
            ]);
        }
    }
}
