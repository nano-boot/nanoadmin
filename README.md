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
