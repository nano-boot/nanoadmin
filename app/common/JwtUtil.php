<?php

namespace plugin\theadmin\app\common;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * JWT工具类
 */
class JwtUtil
{
    /**
     * JWT密钥
     */
    private static string $key = 'theadmin_jwt_secret_key_2024';

    /**
     * JWT算法
     */
    private static string $algorithm = 'HS256';

    /**
     * Token过期时间（秒）
     */
    private static int $expireTime = 7200; // 2小时

    /**
     * 刷新Token过期时间（秒）
     */
    private static int $refreshExpireTime = 604800; // 7天

    /**
     * 生成访问Token
     * @param array $payload 载荷数据
     * @return string
     */
    public static function generateAccessToken(array $payload): string
    {
        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,                              // 签发时间
            'exp' => $now + self::$expireTime,          // 过期时间
            'type' => 'access'                          // Token类型
        ]);

        return JWT::encode($payload, self::$key, self::$algorithm);
    }

    /**
     * 生成刷新Token
     * @param array $payload 载荷数据
     * @return string
     */
    public static function generateRefreshToken(array $payload): string
    {
        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,                                    // 签发时间
            'exp' => $now + self::$refreshExpireTime,         // 过期时间
            'type' => 'refresh'                               // Token类型
        ]);

        return JWT::encode($payload, self::$key, self::$algorithm);
    }

    /**
     * 验证Token
     * @param string $token JWT Token
     * @return array|null 返回解码后的载荷数据，失败返回null
     */
    public static function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$key, self::$algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            // Token已过期
            throw new ApiException(ErrorCode::TOKEN_EXPIRED, 'Token已过期');
        } catch (SignatureInvalidException $e) {
            // 签名无效
            throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token签名无效');
        } catch (BeforeValidException $e) {
            // Token还未生效
            throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token还未生效');
        } catch (\Exception $e) {
            // 其他错误
            throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token无效');
        }
    }

    /**
     * 从Token中获取用户ID
     * @param string $token JWT Token
     * @return int|null
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        try {
            $payload = self::verifyToken($token);
            return $payload['user_id'] ?? null;
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * 检查Token是否为访问Token
     * @param array $payload Token载荷
     * @return bool
     */
    public static function isAccessToken(array $payload): bool
    {
        return ($payload['type'] ?? '') === 'access';
    }

    /**
     * 检查Token是否为刷新Token
     * @param array $payload Token载荷
     * @return bool
     */
    public static function isRefreshToken(array $payload): bool
    {
        return ($payload['type'] ?? '') === 'refresh';
    }

    /**
     * 从请求头中提取Token
     * @param string $authHeader Authorization头部值
     * @return string|null
     */
    public static function extractTokenFromHeader(string $authHeader): ?string
    {
        if (empty($authHeader)) {
            return null;
        }

        // 支持 "Bearer token" 格式
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 直接返回token（兼容性）
        return $authHeader;
    }

    /**
     * 生成Token对（访问Token和刷新Token）
     * @param int $userId 用户ID
     * @param array $extraData 额外数据
     * @return array
     */
    public static function generateTokenPair(int $userId, array $extraData = []): array
    {
        $payload = array_merge([
            'user_id' => $userId,
        ], $extraData);

        return [
            'access_token' => self::generateAccessToken($payload),
            'refresh_token' => self::generateRefreshToken($payload),
            'token_type' => 'Bearer',
            'expires_in' => self::$expireTime,
        ];
    }

    /**
     * 设置JWT密钥
     * @param string $key
     */
    public static function setKey(string $key): void
    {
        self::$key = $key;
    }

    /**
     * 设置Token过期时间
     * @param int $expireTime 过期时间（秒）
     */
    public static function setExpireTime(int $expireTime): void
    {
        self::$expireTime = $expireTime;
    }

    /**
     * 设置刷新Token过期时间
     * @param int $refreshExpireTime 刷新Token过期时间（秒）
     */
    public static function setRefreshExpireTime(int $refreshExpireTime): void
    {
        self::$refreshExpireTime = $refreshExpireTime;
    }

    /**
     * 获取Token剩余有效时间
     * @param string $token JWT Token
     * @return int 剩余秒数，-1表示已过期或无效
     */
    public static function getTokenRemainingTime(string $token): int
    {
        try {
            $payload = self::verifyToken($token);
            $exp = $payload['exp'] ?? 0;
            $remaining = $exp - time();
            return max(0, $remaining);
        } catch (ApiException $e) {
            return -1;
        }
    }
}