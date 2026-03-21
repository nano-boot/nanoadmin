<?php

namespace plugin\theadmin\app\common;

/**
 * 状态码枚举类
 * - 20000: 成功
 * - 40xxx: 业务错误
 * - 50xxx: 服务器错误
 */
enum Code: int
{
    // 成功
    case SUCCESS = 20000;
    // 业务错误 (40xxx)
    case BAD_REQUEST = 40000;            // 客户端请求错误
    case PARAMETER_ERROR = 40001;        // 参数错误
    case LOGIN_FAILED = 40002;           // 登录失败
    case TOKEN_EXPIRED = 40103;          // 令牌已过期
    case TOKEN_INVALID = 40104;          // 令牌无效
    case TOKEN_MISSING = 40105;          // 令牌缺失
    case PASSWORD_ERROR = 40106;         // 密码错误
    case REFRESH_TOKEN_ERROR = 40107;   // 刷新令牌错误
    case REFRESH_TOKEN_MISSING = 40108; // 未提供刷新令
        

    case UNAUTHORIZED = 40100;           // 未授权访问
    case FORBIDDEN = 40301;              // 权限不足
    case ACCOUNT_DISABLED = 40302;       // 账户已禁用
    case NOT_FOUND = 40400;              // 资源不存在
    case ACCOUNT_NOT_FOUND = 40401;      // 账号不存在
    case ROLE_NOT_FOUND = 40402;         // 角色不存在
    case ADMIN_NOT_FOUND = 40403;        // 管理员不存在
    case PERMISSION_NOT_FOUND = 40404;   // 权限不存在
    case MENU_NOT_FOUND = 40405;         // 菜单不存在
    case METHOD_NOT_ALLOWED = 40500;     // 请求方法不允许
    case VALIDATION_ERROR = 40600;       // 数据验证失败
    case DUPLICATE_NAME = 40900;         // 名称已存在
    case HAS_CHILDREN = 40901;           // 存在子项，无法删除
    case DATA_IN_USE = 40902;            // 数据正在使用中，无法删除
    case INVALID_SORT_ORDER = 40903;     // 排序值无效
    case MENU_TRANSFORM_ERROR = 40904;   // 菜单数据转换错误
    case INVALID_JSON_FORMAT = 40905;    // JSON格式错误
    case INVALID_MENU_DATA = 40906;      // 菜单数据格式错误

    // 文件相关错误 (413xx)
    case FILE_UPLOAD_FAILED = 41300;     // 文件上传失败
    case FILE_NOT_FOUND = 41301;         // 文件不存在
    case FILE_TYPE_ERROR = 41302;        // 文件类型不支持
    case FILE_SIZE_ERROR = 41303;        // 文件大小超出限制

    // 支付相关错误 (410xx)
    case PAYMENT_CREATE_FAILED = 41001;   // 支付创建失败
    case PAYMENT_NOTIFY_FAILED = 41002;  // 支付回调处理失败
    case PAYMENT_CANCEL_FAILED = 41003;   // 支付取消失败

    // 服务器错误 (50xxx)
    case SYSTEM_ERROR = 50000;           // 系统错误
    case DATABASE_ERROR = 50001;         // 数据库错误
    case CACHE_ERROR = 50002;            // 缓存错误
    case NETWORK_ERROR = 50003;          // 网络错误

    /**
     * 获取错误消息
     */
    public function getMessage(): string
    {
        return match($this) {
            self::SUCCESS => '操作成功',

            // 业务错误
            self::PARAMETER_ERROR => '参数错误',
            self::UNAUTHORIZED => '未授权访问',
            self::LOGIN_FAILED => '登录失败',
            self::TOKEN_EXPIRED => '令牌已过期',
            self::TOKEN_INVALID => '令牌无效',
            self::TOKEN_MISSING => '令牌缺失',
            self::PASSWORD_ERROR => '密码错误',
            self::ACCOUNT_DISABLED => '账户已禁用',
            self::FORBIDDEN => '权限不足',
            self::NOT_FOUND => '资源不存在',
            self::METHOD_NOT_ALLOWED => '请求方法不允许',
            self::VALIDATION_ERROR => '数据验证失败',
            self::DUPLICATE_NAME => '名称已存在',
            self::HAS_CHILDREN => '存在子项，无法删除',
            self::DATA_IN_USE => '数据正在使用中，无法删除',
            self::INVALID_SORT_ORDER => '排序值无效',
            self::MENU_TRANSFORM_ERROR => '菜单数据转换错误',
            self::INVALID_JSON_FORMAT => 'JSON格式错误',
            self::INVALID_MENU_DATA => '菜单数据格式错误',

            // 业务资源不存在
            self::MENU_NOT_FOUND => '菜单不存在',
            self::ROLE_NOT_FOUND => '角色不存在',
            self::ADMIN_NOT_FOUND => '管理员不存在',
            self::PERMISSION_NOT_FOUND => '权限不存在',

            // 文件相关错误
            self::FILE_UPLOAD_FAILED => '文件上传失败',
            self::FILE_NOT_FOUND => '文件不存在',
            self::FILE_TYPE_ERROR => '文件类型不支持',
            self::FILE_SIZE_ERROR => '文件大小超出限制',

            // 支付相关错误
            self::PAYMENT_CREATE_FAILED => '支付创建失败',
            self::PAYMENT_NOTIFY_FAILED => '支付回调处理失败',
            self::PAYMENT_CANCEL_FAILED => '支付取消失败',

            // 服务器错误
            self::SYSTEM_ERROR => '系统错误',
            self::DATABASE_ERROR => '数据库错误',
            self::CACHE_ERROR => '缓存错误',
            self::NETWORK_ERROR => '网络错误',

            default => '未知错误',
        };
    }

    /**
     * 获取对应的HTTP状态码
     */
    public function getHttpCode(): int
    {
        return match($this) {
            self::SUCCESS => 200,

            // 400系列错误
            self::PARAMETER_ERROR,
            self::VALIDATION_ERROR,
            self::DUPLICATE_NAME,
            self::HAS_CHILDREN,
            self::DATA_IN_USE,
            self::INVALID_SORT_ORDER,
            self::MENU_TRANSFORM_ERROR,
            self::INVALID_JSON_FORMAT,
            self::INVALID_MENU_DATA => 400,

            // 401未授权
            self::UNAUTHORIZED,
            self::LOGIN_FAILED,
            self::TOKEN_EXPIRED,
            self::TOKEN_INVALID,
            self::TOKEN_MISSING,
            self::PASSWORD_ERROR,
            self::ACCOUNT_DISABLED => 401,

            // 403权限不足
            self::FORBIDDEN => 403,

            // 404资源不存在
            self::NOT_FOUND,
            self::MENU_NOT_FOUND,
            self::ROLE_NOT_FOUND,
            self::ADMIN_NOT_FOUND,
            self::PERMISSION_NOT_FOUND,
            self::FILE_NOT_FOUND => 404,

            // 405方法不允许
            self::METHOD_NOT_ALLOWED => 405,

            // 413文件过大
            self::FILE_SIZE_ERROR => 413,

            // 410支付相关错误
            self::PAYMENT_CREATE_FAILED,
            self::PAYMENT_NOTIFY_FAILED,
            self::PAYMENT_CANCEL_FAILED => 400,

            // 415不支持的媒体类型
            self::FILE_TYPE_ERROR => 415,

            // 422处理失败
            self::FILE_UPLOAD_FAILED => 422,

            // 500服务器错误
            self::SYSTEM_ERROR,
            self::DATABASE_ERROR,
            self::CACHE_ERROR,
            self::NETWORK_ERROR => 500,

            default => 500,
        };
    }

    /**
     * 静态方法：根据错误码获取消息（兼容旧代码）
     */
    public static function getMessageByCode(int $code): string
    {
        $errorCode = self::tryFrom($code);
        return $errorCode ? $errorCode->getMessage() : '未知错误';
    }

    /**
     * 静态方法：根据错误码获取HTTP状态码（兼容旧代码）
     */
    public static function getHttpCodeByCode(int $code): int
    {
        $errorCode = self::tryFrom($code);
        return $errorCode ? $errorCode->getHttpCode() : 500;
    }
}