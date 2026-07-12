<?php

namespace plugin\nanoadmin\app\common;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use support\Request;

/**
 * JWT工具类
 *
 * 配置从 config('plugin.nanoadmin.jwt') 读取（Webman 配置解析器优先查找项目自身
 * plugin/nanoadmin/config/nanoadmin.php）。
 */
class JwtUtil
{
    /**
     * 默认配置（兜底，仅在 config 不可用时使用）
     */
    private const DEFAULT_KEY = 'nanoadmin_jwt_secret_key_2024';
    private const DEFAULT_ALGORITHM = 'HS256';
    private const DEFAULT_EXPIRE_TIME = 7200;     // 2小时
    private const DEFAULT_REFRESH_EXPIRE_TIME = 604800; // 7天

    /**
     * 运行时覆盖缓存（多用于测试）。值为 null 表示未覆盖，走 config / DEFAULT。
     */
    private static ?string $overrideKey = null;
    private static ?int $overrideExpireTime = null;
    private static ?int $overrideRefreshExpireTime = null;

    /**
     * 读取插件 jwt 配置项，缺省回退到 DEFAULT_*。
     *
     * @param string $key 配置键
     * @param mixed $default 兜底值
     * @return mixed
     */
    private static function jwtConfig(string $key, mixed $default): mixed
    {
        try {
            $config = function_exists('config') ? config('plugin.nanoadmin.jwt', []) : [];
        } catch (\Throwable $e) {
            $config = [];
        }
        return $config[$key] ?? $default;
    }

    private static function key(): string
    {
        if (self::$overrideKey !== null) {
            return self::$overrideKey;
        }
        $env = getenv('JWT_SECRET');
        return is_string($env) && $env !== '' ? $env : (string) self::jwtConfig('secret', self::DEFAULT_KEY);
    }

    private static function algorithm(): string
    {
        return (string) self::jwtConfig('algorithm', self::DEFAULT_ALGORITHM);
    }

    private static function expireTime(): int
    {
        return self::$overrideExpireTime ?? (int) self::jwtConfig('expire_time', self::DEFAULT_EXPIRE_TIME);
    }

    private static function refreshExpireTime(): int
    {
        return self::$overrideRefreshExpireTime ?? (int) self::jwtConfig('refresh_expire_time', self::DEFAULT_REFRESH_EXPIRE_TIME);
    }

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
            'exp' => $now + self::expireTime(),          // 过期时间
            'type' => 'access'                          // Token类型
        ]);

        return JWT::encode($payload, self::key(), self::algorithm());
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
            'exp' => $now + self::refreshExpireTime(),         // 过期时间
            'type' => 'refresh'                               // Token类型
        ]);

        return JWT::encode($payload, self::key(), self::algorithm());
    }

    /**
     * 验证Token
     * @param string $token JWT Token
     * @return array|null 返回解码后的载荷数据，失败返回null
     * @throws ApiException
     */
    public static function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::key(), self::algorithm()));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            // Token已过期
            throw new ApiException(Code::TOKEN_EXPIRED, '登录已过期');
        } catch (SignatureInvalidException $e) {
            // 签名无效
            throw new ApiException(Code::TOKEN_INVALID, 'Token签名无效');
        } catch (BeforeValidException $e) {
            // Token还未生效
            throw new ApiException(Code::TOKEN_INVALID, 'Token还未生效');
        } catch (\Exception $e) {
            // 其他错误
            throw new ApiException(Code::TOKEN_INVALID, 'Token无效');
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
            'expires_in' => self::expireTime(),
        ];
    }

    /**
     * 设置JWT密钥
     * @param string $key
     */
    public static function setKey(string $key): void
    {
        self::$overrideKey = $key;
    }

    /**
     * 设置Token过期时间
     * @param int $expireTime 过期时间（秒）
     */
    public static function setExpireTime(int $expireTime): void
    {
        self::$overrideExpireTime = $expireTime;
    }

    /**
     * 设置刷新Token过期时间
     * @param int $refreshExpireTime 刷新Token过期时间（秒）
     */
    public static function setRefreshExpireTime(int $refreshExpireTime): void
    {
        self::$overrideRefreshExpireTime = $refreshExpireTime;
    }

    /**
     * 生成访问 Token（用于普通业务场景，无需 refresh_token 配对）
     *
     * @param int $userId 用户ID
     * @param array $extra 额外载荷（会原样写入 payload）
     * @return string
     */
    public static function generateToken(int $userId, array $extra = []): string
    {
        $now = time();
        $payload = array_merge([
            'user_id' => $userId,
        ], $extra, [
            'iat' => $now,
            'exp' => $now + self::expireTime(),
            'type' => 'access',
        ]);

        return JWT::encode($payload, self::key(), self::algorithm());
    }

    /**
     * 刷新 Token：基于当前 token 签发新的 access token。
     *
     * 不传 token 时从当前请求读取，提取逻辑与 AuthMiddleware 保持一致：
     * Authorization: Bearer → X-Token → 抛 ApiException(TOKEN_MISSING)。
     *
     * @param string|null $token 可选；不传则从当前请求读取
     * @return string 新的 access token
     * @throws ApiException
     */
    public static function refreshToken(?string $token = null): string
    {
        $token ??= self::extractFromRequest();
        $payload = self::verifyToken($token);

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new ApiException(Code::TOKEN_INVALID, 'Token中缺少用户信息');
        }

        // 保留业务字段，过滤标准 JWT 字段
        $business = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, ['iat', 'exp', 'nbf', 'iss', 'aud', 'jti', 'type'], true)) {
                continue;
            }
            $business[$key] = $value;
        }

        return self::generateToken($userId, $business);
    }

    /**
     * 从 Token 中获取用户ID
     *
     * 不传 token 时从当前请求读取（与 refreshToken 走同一套 extractFromRequest）。
     *
     * @param string|null $token 可选；不传则从当前请求读取
     * @return int 用户ID，未取到返回 0
     * @throws ApiException
     */
    public static function getId(?string $token = null): int
    {
        $token ??= self::extractFromRequest();
        $payload = self::verifyToken($token);
        return (int) ($payload['user_id'] ?? 0);
    }

    /**
     * 从当前请求头解析 Token（与 AuthMiddleware 提取逻辑保持一致）
     *
     * 优先级：Authorization: Bearer {token} → X-Token → 抛 ApiException(TOKEN_MISSING)
     *
     * @return string
     * @throws ApiException
     */
    public static function extractFromRequest(): string
    {
        /** @var Request|null $request */
        $request = function_exists('request') ? request() : null;
        $header = $request ? (string) $request->header('Authorization', '') : '';
        $token = self::extractTokenFromHeader($header);

        if ($token === null || $token === '') {
            $xToken = $request ? (string) $request->header('X-Token', '') : '';
            if ($xToken !== '') {
                return $xToken;
            }
            throw new ApiException(Code::TOKEN_MISSING, '缺少认证Token');
        }

        return $token;
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