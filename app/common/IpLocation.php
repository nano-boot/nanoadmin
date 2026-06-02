<?php

namespace plugin\theadmin\app\common;

/**
 * IP 归属地查询工具
 *
 * 支持两种模式：
 * 1. 在线模式（默认）：调用 ip-api.com
 * 2. 本地模式：接入 ip2region 等离线库
 *
 * 切换方式：设置 IPLOCATION_DRIVER 环境变量
 *
 * 本地模式建议将 ip2region.xdb 放到 runtime/ip2region/ip2region.xdb
 */
class IpLocation
{
    /**
     * 驱动类型：online | local
     * @var string
     */
    protected string $driver = 'online';

    /**
     * 在线 API 地址（ip-api.com）
     * @var string
     */
    protected const ONLINE_API = 'http://ip-api.com/json/%s?fields=country,regionName,city,isp,status,message';

    /**
     * 本地库默认路径
     * @var string
     */
    protected const LOCAL_DB_PATH = '';

    public function __construct()
    {
        $envDriver = env('IPLOCATION_DRIVER', '');
        if (in_array($envDriver, ['online', 'local'], true)) {
            $this->driver = $envDriver;
        }
    }

    /**
     * 查询 IP 归属地
     *
     * @param string $ip IP 地址
     * @return string 归属地字符串，失败返回空字符串
     */
    public function get(string $ip): string
    {
        if (empty($ip) || $this->isPrivateIp($ip)) {
            return '内网IP';
        }

        return $this->driver === 'local'
            ? $this->queryLocal($ip)
            : $this->queryOnline($ip);
    }

    /**
     * 在线查询（HTTP 请求）
     *
     * @param string $ip
     * @return string
     */
    protected function queryOnline(string $ip): string
    {
        $url = sprintf(self::ONLINE_API, $ip);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return '';
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return '';
        }

        $parts = array_filter([
            $data['country'] ?? '',
            $data['regionName'] ?? '',
            $data['city'] ?? '',
            $data['isp'] ?? '',
        ]);

        return empty($parts) ? '' : implode(' ', $parts);
    }

    /**
     * 本地离线查询（优先使用 ip2region xdb）
     *
     * @param string $ip
     * @return string
     */
    protected function queryLocal(string $ip): string
    {
        $dbPath = self::LOCAL_DB_PATH ?: base_path() . '/runtime/ip2region/ip2region.xdb';

        if (!is_file($dbPath)) {
            return '';
        }

        if (class_exists('Ip2Region\Ip2Region')) {
            try {
                $ip2region = new \Ip2Region\Ip2Region($dbPath);
                $result = $ip2region->btreeSearch($ip);

                if (is_array($result)) {
                    $region = $result['region'] ?? $result['regionStr'] ?? $result['region_str'] ?? '';
                    if (!empty($region)) {
                        return $this->normalizeRegion((string) $region);
                    }
                }

                if (is_string($result) && !empty($result)) {
                    return $this->normalizeRegion($result);
                }
            } catch (\Throwable $e) {
                return '';
            }
        }

        return '';
    }

    /**
     * 归一化 ip2region 返回值
     *
     * @param string $region
     * @return string
     */
    protected function normalizeRegion(string $region): string
    {
        $region = trim($region);
        if ($region === '') {
            return '';
        }

        $parts = array_filter(explode('|', $region), static fn ($value) => $value !== '' && $value !== '0' && $value !== '内网IP');
        if (empty($parts)) {
            return '';
        }

        $filtered = [];
        foreach ($parts as $part) {
            if ($part === 'China' || $part === '中国') {
                $filtered[] = '中国';
                continue;
            }

            if (in_array($part, ['0', '内网IP', '内网'], true)) {
                continue;
            }

            $filtered[] = $part;
        }

        $filtered = array_values(array_unique($filtered));

        return implode(' ', $filtered);
    }

    /**
     * 判断是否为内网 IP
     *
     * @param string $ip
     * @return bool
     */
    protected function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
