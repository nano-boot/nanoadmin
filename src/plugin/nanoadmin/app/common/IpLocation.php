<?php

namespace plugin\nanoadmin\app\common;

/**
 * IP 归属地查询工具
 *
 * 本地模式：接入 zoujingli/ip2region 离线库
 *
 * 默认使用 Composer 包内置的 IPv4 数据库
 * 也可以通过 IPLOCATION_DB_PATH 环境变量指定自定义 IPv4 xdb 路径
 */
class IpLocation
{
    /**
     * 本地库默认路径
     * @var string
     */
    protected const LOCAL_DB_PATH = '';

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

        return $this->queryLocal($ip);
    }

    /**
     * 本地离线查询（优先使用 ip2region xdb）
     *
     * @param string $ip
     * @return string
     */
    protected function queryLocal(string $ip): string
    {
        try {
            $dbPath = $this->getLocalDbPath();
            $ip2region = $dbPath !== null ? new \Ip2Region('file', $dbPath) : new \Ip2Region();
            $result = $ip2region->search($ip);

            return is_string($result) && $result !== ''
                ? $this->normalizeRegion($result)
                : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * 获取自定义本地数据库路径
     *
     * @return string|null
     */
    protected function getLocalDbPath(): ?string
    {
        $envDbPath = trim((string) env('IPLOCATION_DB_PATH', ''));
        if ($envDbPath !== '') {
            return $envDbPath;
        }

        $localDbPath = trim(self::LOCAL_DB_PATH);

        return $localDbPath !== '' ? $localDbPath : null;
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

        $parts = array_map('trim', explode('|', $region));
        $lastPart = end($parts);
        if (is_string($lastPart) && preg_match('/^[A-Z]{2}$/', $lastPart) === 1) {
            array_pop($parts);
        }

        $parts = array_filter($parts, static fn ($value) => $value !== '' && $value !== '0' && $value !== '内网IP');
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
