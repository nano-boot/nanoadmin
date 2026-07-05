<?php
/**
 * Here is your custom functions.
 */

if (!function_exists('plugin_nanoadmin_path')) {
    /**
     * 获取 nanoadmin 插件的目录路径
     * @param string $path 相对于插件目录的路径
     * @return string
     */
    function plugin_nanoadmin_path(string $path = ''): string
    {
        // plugin/nanoadmin/app/ -> plugin/ (向上2级) -> plugin/nanoadmin/
        $basePath = dirname(__DIR__, 2) . '/nanoadmin';
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

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

if(!function_exists('domain')){
    function domain():string
    {
        // 优先使用环境变量中的域名
        if ($envDomain = env('APP_URL')) {
            return $envDomain;
        }
        
        try {
            $request = request();
            $host = $request->host();
            $scheme = $request->header('x-forwarded-proto')
                     ?: $request->header('x-forwarded-protocol')
                     ?: $request->header('x-scheme')
                     ?: 'http'; 

            return $scheme . '://' . $host;
        } catch (\Exception $e) {
            // 如果获取失败，返回默认值
            return '';
        }
    }
}
