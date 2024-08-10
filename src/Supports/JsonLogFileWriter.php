<?php namespace Haruncpi\QueryLog\Supports;

use Haruncpi\QueryLog\Contracts\FileWritable;

class JsonLogFileWriter implements FileWritable
{
    const QUERY_LOG_FORMAT_FLAG = 0;

    public function write($file_path, $data)
    {
        // Check if the file exists and is not empty
        if (file_exists($file_path) && filesize($file_path) > 0) {
            // Read the existing content
            $existingData = json_decode(file_get_contents($file_path), true);

            // Append the new data
            $existingData[] = $data;
        } else {
            // If the file doesn't exist or is empty, create a new array
            $existingData = [$data];
        }
        $flag = env('QUERY_LOG_FORMAT_FLAG', self::QUERY_LOG_FORMAT_FLAG);
        
        // Write the combined data back to the file
        file_put_contents($file_path, json_encode($existingData, $flag));
    }
}