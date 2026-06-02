<?php

namespace plugin\theadmin\app\common;

/**
 * IP 归属地查询工具
 *
 * 支持两种模式：
 * 1. 在线模式（默认）：调用 ip-api.com（免费，无 Key，适合内网开发/小并发）
 * 2. 本地模式：接入 ip2region 等离线库（生产环境推荐）
 *
 * 切换方式：设置 IPLOCATION_DRIVER 环境变量或修改 defaultDriver
 */
class IpLocation
{
    /**
     * 驱动类型：online | local
     * @var string
     */
    protected string $driver = 'online';

    /**
     * 在线 API 地址（ip-api.com，免费 45 req/min，支持 IPv4/IPv6）
     * @var string
     */
    protected const ONLINE_API = 'http://ip-api.com/json/%s?fields=country,regionName,city,isp,status,message';

    /**
     * 离线库路径（ip2region.db，下载至 runtime/ 目录后启用）
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
     * @return string 归属地字符串，如 "中国 广东省 深圳市 电信"，失败返回空字符串
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
        ]);

        if (empty($parts)) {
            return '';
        }

        return implode(' ', $parts);
    }

    /**
     * 本地离线查询（需要预先配置 ip2region 等本地库）
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

        // TODO: 接入 ip2region 本地库，示例：
        // return IpLocationLocal::search($dbPath, $ip);
        return '';
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
