# Nano Admin Plugin

基于 Workerman/Webman 的后台管理系统核心插件。

## 安装

```bash
composer require nano-boot/nanoadmin
```

## 目录结构

```
plugin/nanoadmin/
├── app/                    # 应用代码
│   ├── common/            # 公共类
│   ├── config/            # 配置目录
│   ├── controller/        # 控制器
│   ├── middleware/        # 中间件
│   ├── model/             # 数据模型
│   ├── route/             # 路由
│   ├── service/           # 服务层
│   ├── validator/         # 验证器
│   └── functions.php      # 公共函数
├── config/                 # 插件配置
├── database/               # 数据库迁移
├── sql/                    # SQL 脚本
└── tests/                  # 单元测试
```

## 开发

```bash
# 安装依赖
composer install

# 运行测试
composer test
```

## 依赖

- PHP >= 8.1
- ext-pdo
- ext-json
- illuminate/database ^10.0|^11.0
- firebase/php-jwt ^6.11

## 作为 webman 插件安装

本仓库**同时**也是一个 webman 插件包（`type: webman-plugin`）。当其他 webman 主项目执行 `composer require nano-boot/nanoadmin` 时：

1. **主项目 composer.json** 的 `post-package-install/update` 触发 `support\\Plugin::install`
2. **webman 框架**通过 psr-4 autoload 找到 `Webman\nanoadmin\Install`（识别条件：`WEBMAN_PLUGIN = true`）
3. **Install::install()** 把仓库根下的 `app/`、`config/`、`database/`、`sql/`、`api/` 复制到主项目 `plugin/nanoadmin/`
4. 复制使用 webman 的 `copy_dir()`，默认**不覆盖已有文件**，用户本地修改的配置会保留
5. `composer remove nano-boot/nanoadmin` 时 `Install::uninstall()` 会删除主项目 `plugin/nanoadmin/`

主项目最低配置要求（参考 saiadmin）：

```json
{
    "scripts": {
        "post-package-install":  ["support\\Plugin::install"],
        "post-package-update":   ["support\\Plugin::install"],
        "pre-package-uninstall": ["support\\Plugin::uninstall"]
    },
    "autoload": {
        "psr-4": {
            "plugin\\": "./plugin"
        }
    }
}
```

### 命名空间约定

| 命名空间 | 路径 | 用途 |
|---------|------|------|
| `Webman\nanoadmin\` | 仓库根 | 仅 `Install.php`（webman 插件入口） |
| `plugin\nanoadmin\app\` | `app/` | 业务代码（主项目通过 `plugin\` 命名空间加载） |
| `plugin\nanoadmin\api\` | `api/` | 业务 API 辅助类 |
