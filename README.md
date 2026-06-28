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

## 升级

本插件遵循 [SemVer](https://semver.org/lang/zh-CN/) 语义化版本。每次代码更新都会打新的 git tag,Packagist 不会自动跟随 main 分支。

```bash
# 查看已安装版本
composer show nano-boot/nanoadmin

# 升级到最新版本
composer update nano-boot/nanoadmin

# 升级到指定版本
composer require nano-boot/nanoadmin:^1.0

# 锁定到精确版本(避免自动升级)
composer require nano-boot/nanoadmin:1.0.1
```

版本号含义:
- `1.0.1` → 1.0.1:补丁版本,bug 修复,完全兼容
- `1.1.0` → 次版本,新增功能,向后兼容
- `2.0.0` → 主版本,**可能有破坏性变更**,升级前必看 CHANGELOG

## 版本管理

- 本项目版本号与 git tag 严格绑定
- tag 不可变,新代码必须通过新 tag 发布
- 不建议强制覆盖已发布的 tag

## 作为 webman 插件安装

本仓库**同时**也是一个 webman 插件包（`type: webman-plugin`）。当其他 webman 主项目执行 `composer require nano-boot/nanoadmin` 时：

1. **主项目 composer.json** 的 `post-package-install/update` 触发 `support\\Plugin::install`
2. **webman 框架**通过 psr-4 autoload 找到 `Webman\nanoadmin\Install`（识别条件：`WEBMAN_PLUGIN = true`）
3. **Install::install()** 把仓库根下的 `app/`、`config/`、`database/`、`sql/`、`api/` 复制到主项目 `plugin/nanoadmin/`
4. 复制使用 webman 的 `copy_dir()`，默认**不覆盖已有文件**，用户本地修改的配置会保留
5. `composer remove nano-boot/nanoadmin` 时 `Install::uninstall()` 会删除主项目 `plugin/nanoadmin/`

主项目最低配置要求：

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
