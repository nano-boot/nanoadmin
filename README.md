# TheAdmin 权限管理系统

基于Webman框架的RBAC权限管理系统，提供完整的管理员、角色、权限、菜单管理功能。

## 功能特性

- 🔐 完整的RBAC权限控制
- 👥 管理员账户管理
- 🎭 角色管理和权限分配
- 🔑 细粒度权限控制
- 📋 动态菜单管理
- 🛡️ JWT认证机制
- 📊 操作日志记录
- 🎨 响应式前端界面

## 系统要求

- PHP >= 8.0
- MySQL >= 5.7
- Webman >= 1.5
- ThinkORM >= 2.0

## 安装步骤

### 1. 数据库配置

修改 `config/think-orm.php` 文件中的数据库连接信息：

```php
'connections' => [
    'mysql' => [
        'hostname' => '127.0.0.1',
        'database' => 'theadmin',
        'username' => 'root',
        'password' => '123456',
        'hostport' => '3306',
    ],
],
```

### 2. 执行安装脚本

```bash
php plugin/theadmin/simple_install.php
```

### 3. 默认账号

安装完成后，系统会创建默认管理员账号：

- 用户名：`admin`
- 密码：`admin123`

**请及时修改默认密码！**

## 目录结构

```
plugin/theadmin/
├── app/
│   ├── common/          # 通用类
│   ├── controller/      # 控制器
│   ├── middleware/      # 中间件
│   ├── model/          # 模型
│   └── service/        # 服务层
├── config/             # 配置文件
├── database/           # 数据库相关
├── sql/               # SQL脚本
└── README.md          # 说明文档
```

## 数据库表结构

### 核心表

- `th_sys_admin` - 管理员表
- `th_sys_role` - 角色表
- `th_sys_permission` - 权限表
- `th_sys_menu` - 菜单表

### 关联表

- `th_sys_admin_role` - 管理员角色关联表
- `th_sys_role_permission` - 角色权限关联表
- `th_sys_role_menu` - 角色菜单关联表

## 模型使用

### 基础模型

所有模型都继承自 `BaseModel`，提供了通用的CRUD方法：

```php
use plugin\theadmin\app\model\Admin;

$admin = new Admin();

// 获取列表
$list = $admin->getList(['status' => 1], 1, 15);

// 创建记录
$result = $admin->add(['username' => 'test', 'password' => '123456']);

// 更新记录
$result = $admin->edit(['nickname' => '测试'], ['id' => 1]);

// 删除记录（软删除）
$result = $admin->remove(1);
```

### 管理员模型

```php
use plugin\theadmin\app\model\Admin;

$admin = new Admin();

// 验证密码
$isValid = $admin->verifyPassword('123456');

// 获取权限
$permissions = $admin->getPermissions();

// 获取菜单
$menus = $admin->getMenus();

// 检查权限
$hasPermission = $admin->hasPermission('admin.create');

// 分配角色
$admin->assignRoles([1, 2, 3]);
```

### 角色模型

```php
use plugin\theadmin\app\model\Role;

$role = new Role();

// 分配权限
$role->assignPermissions([1, 2, 3]);

// 分配菜单
$role->assignMenus([1, 2, 3]);

// 检查是否被使用
$isUsed = $role->isUsed(1);
```

### 权限模型

```php
use plugin\theladmin\app\model\Permission;

$permission = new Permission();

// 获取权限树
$tree = $permission->getTree();

// 根据资源和操作获取权限
$perm = $permission->getByResourceAction('admin', 'create');
```

### 菜单模型

```php
use plugin\theadmin\app\model\Menu;

$menu = new Menu();

// 获取菜单树
$tree = $menu->getTree();

// 获取用户菜单
$userMenus = $menu->getUserMenuTree([1, 2]);

// 更新菜单排序
$menu->updateMenuSort([
    ['id' => 1, 'sort' => 10],
    ['id' => 2, 'sort' => 20]
]);
```

## 模型工厂

使用模型工厂可以方便地获取模型实例：

```php
use plugin\theadmin\app\model\ModelFactory;

// 获取模型实例
$admin = ModelFactory::admin();
$role = ModelFactory::role();
$permission = ModelFactory::permission();
$menu = ModelFactory::menu();
```

## 配置说明

### 权限配置

在 `config/permission.php` 中可以配置：

- JWT相关设置
- 权限缓存配置
- 安全策略配置
- 分页配置等

### 数据库配置

在 `config/think-orm.php` 中配置数据库连接信息。

## 开发指南

### 添加新权限

1. 在数据库中添加权限记录
2. 在角色管理中分配权限
3. 在代码中使用权限验证

### 添加新菜单

1. 在菜单管理中添加菜单项
2. 设置菜单权限标识
3. 在角色管理中分配菜单

### 扩展模型

继承 `BaseModel` 创建新的模型：

```php
namespace plugin\theadmin\app\model;

class CustomModel extends BaseModel
{
    protected $table = 'custom_table';
    
    // 自定义方法
}
```

## 注意事项

1. 请及时修改默认管理员密码
2. 定期备份数据库
3. 合理设置权限，遵循最小权限原则
4. 注意数据库表前缀设置
5. 生产环境请关闭调试模式

## 更新日志

### v1.0.0
- 初始版本发布
- 完整的RBAC权限管理功能
- 基础的管理员、角色、权限、菜单管理

## 技术支持

如有问题，请提交Issue或联系开发团队。

## 许可证

MIT License