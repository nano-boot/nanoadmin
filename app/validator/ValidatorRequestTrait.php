<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator;

use support\Request;

/**
 * 验证器请求Trait
 *
 * 提供请求相关的方法，类似 think-validate 的风格
 */
trait ValidatorRequestTrait
{
    /**
     * 当前请求实例
     */
    protected ?Request $request = null;

    /**
     * 获取请求实例
     */
    protected function getRequest(): Request
    {
        if ($this->request === null) {
            $this->request = request();
        }
        return $this->request;
    }

    /**
     * 获取GET参数
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->getRequest()->get($name, $default);
    }

    /**
     * 获取POST参数
     */
    public function post(string $name, mixed $default = null): mixed
    {
        return $this->getRequest()->post($name, $default);
    }

    /**
     * 获取输入参数
     */
    public function input(string $name, mixed $default = null): mixed
    {
        return $this->getRequest()->input($name, $default);
    }

    /**
     * 获取所有参数
     */
    public function all(): array
    {
        return $this->getRequest()->all();
    }

    /**
     * 获取请求方法
     */
    public function method(): string
    {
        return $this->getRequest()->method();
    }

    /**
     * 获取请求路径
     */
    public function path(): string
    {
        return $this->getRequest()->path();
    }

    /**
     * 是否GET请求
     */
    public function isGet(): bool
    {
        return $this->getRequest()->method() === 'GET';
    }

    /**
     * 是否POST请求
     */
    public function isPost(): bool
    {
        return $this->getRequest()->method() === 'POST';
    }

    /**
     * 是否AJAX请求
     */
    public function isAjax(): bool
    {
        return $this->getRequest()->isAjax();
    }

    /**
     * 获取客户端IP
     */
    public function getIp(): string
    {
        return $this->getRequest()->getRealIp();
    }

    /**
     * 检查参数是否存在
     */
    public function has(string $key): bool
    {
        return $this->getRequest()->has($key);
    }

    /**
     * 获取上传文件
     */
    public function file(string $key): mixed
    {
        return $this->getRequest()->file($key);
    }

    /**
     * 获取所有上传文件
     */
    public function files(): array
    {
        return $this->getRequest()->file();
    }

    /**
     * 魔术方法：代理请求对象的方法
     */
    public function __call(string $method, array $args): mixed
    {
        $request = $this->getRequest();
        if (method_exists($request, $method)) {
            return $request->{$method}(...$args);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on Request");
    }
}
