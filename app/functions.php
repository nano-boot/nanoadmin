<?php
/**
 * Here is your custom functions.
 */

if (!function_exists('record')) {
    function record($data, $flag = 'debug'): void
    {
        $timestamp = time();
        $date = date('y/m', $timestamp);
        $day = date('d', $timestamp);
        $time = date('H:i:s', $timestamp);
        $path = run_path("runtime/record/{$flag}/{$date}");
        $file = "{$path}/{$day}.log";
        try {
            if(!file_exists($path)){
                mkdir($path,0755,true);
            }
            if(!is_file($file)){
                fopen($file, "w",true);
            }
            file_put_contents( $file, "[{$time}]". PHP_EOL .
                json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL.
                '================='. PHP_EOL,
                FILE_APPEND);
        }catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
