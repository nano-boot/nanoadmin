# Menu 模型字段映射说明

## 概述

Menu 模型已经实现了 **数据库下划线命名** 和 **前端驼峰命名** 之间的自动转换。使用 Eloquent ORM 的 **Accessors（访问器）** 和 **Mutators（修改器）** 特性完成。

## 字段映射关系

| 数据库字段 (snake_case) | 前端字段 (camelCase) | 类型 | 说明 |
|----------------------|-------------------|------|------|
| `cache` | `keepAlive` | boolean | 是否缓存页面 |
| `fixed_tab` | `fixedTab` | boolean | 是否固定标签页 |
| `full_page` | `fullPage` | boolean | 是否全屏显示 |
| `show_badge` | `showBadge` | boolean | 是否显示徽章 |
| `link_url` | `linkUrl` | string | 外链地址 |
| `badge_text` | `badgeText` | string | 徽章文本 |
| `active_path` | `activePath` | string | 激活路径 |
| `created_at` | `createdAt` | string | 创建时间 |
| `updated_at` | `updatedAt` | string | 更新时间 |

## 使用示例

### 1. 从数据库读取（自动转换为驼峰命名）

```php
use plugin\theadmin\app\model\Menu;

// 查询菜单
$menu = Menu::find(1);

// 转换为数组（自动应用访问器，输出驼峰命名）
$menuArray = $menu->toArray();

/*
返回结果：
[
    'id' => 1,
    'name' => 'dashboard',
    'title' => '仪表盘',
    'keepAlive' => true,        // 自动从 cache 字段转换
    'fixedTab' => false,        // 自动从 fixed_tab 字段转换
    'fullPage' => false,        // 自动从 full_page 字段转换
    'showBadge' => true,        // 自动从 show_badge 字段转换
    'linkUrl' => '',            // 自动从 link_url 字段转换
    'badgeText' => 'New',       // 自动从 badge_text 字段转换
    'activePath' => '/dashboard', // 自动从 active_path 字段转换
    'createdAt' => '2024-01-01 00:00:00',  // 自动从 created_at 转换
    'updatedAt' => '2024-01-01 00:00:00',  // 自动从 updated_at 转换
    // ... 其他字段
]

注意：原始的下划线字段已被隐藏，不会出现在数组中
*/
```

### 2. 使用驼峰命名创建菜单

```php
use plugin\theadmin\app\model\Menu;

// 方式 1: 使用驼峰命名字段（推荐）
$menu = Menu::create([
    'name' => 'user-management',
    'title' => '用户管理',
    'path' => '/system/user',
    'component' => 'UserManagement',
    'keepAlive' => true,      // 自动映射到 cache 字段
    'fixedTab' => false,      // 自动映射到 fixed_tab 字段
    'fullPage' => false,      // 自动映射到 full_page 字段
    'showBadge' => true,      // 自动映射到 show_badge 字段
    'badgeText' => 'Hot',     // 自动映射到 badge_text 字段
]);

// 方式 2: 使用下划线命名（仍然支持）
$menu = Menu::create([
    'name' => 'user-management',
    'title' => '用户管理',
    'path' => '/system/user',
    'component' => 'UserManagement',
    'cache' => true,          // 直接使用数据库字段名
    'fixed_tab' => false,
    'full_page' => false,
    'show_badge' => true,
    'badge_text' => 'Hot',
]);

// 两种方式都可以，推荐使用驼峰命名保持前后端一致
```

### 3. 更新菜单（支持驼峰命名）

```php
use plugin\theadmin\app\model\Menu;

$menu = Menu::find(1);

// 使用驼峰命名更新（推荐）
$menu->update([
    'keepAlive' => false,
    'fixedTab' => true,
    'badgeText' => 'Updated',
]);

// 或者直接赋值
$menu->keepAlive = true;
$menu->fixedTab = false;
$menu->save();
```

### 4. 在 MenuTransformService 中的应用

```php
use plugin\theadmin\app\service\MenuTransformService;
use plugin\theadmin\app\model\Menu;

$transformService = new MenuTransformService();

// 获取菜单数据
$menu = Menu::find(1);

// 格式化为 API 响应（传入模型实例时，自动使用访问器）
$formattedData = $transformService->formatForApi($menu);

/*
返回的数组已经是驼峰命名：
[
    'id' => 1,
    'name' => 'dashboard',
    'keepAlive' => true,
    'fixedTab' => false,
    'fullPage' => false,
    'showBadge' => true,
    'linkUrl' => '',
    'badgeText' => 'New',
    'activePath' => '/dashboard',
    'createdAt' => '2024-01-01 00:00:00',
    'updatedAt' => '2024-01-01 00:00:00',
    // ...
]
*/
```

### 5. 批量查询与转换

```php
use plugin\theadmin\app\model\Menu;

// 查询所有菜单
$menus = Menu::where('status', 1)->get();

// 转换为数组（自动应用访问器）
$menusArray = $menus->toArray();

// 每个菜单都会自动转换字段名称
foreach ($menusArray as $menu) {
    echo $menu['keepAlive'];  // true/false
    echo $menu['fixedTab'];   // true/false
    // 可以直接使用驼峰命名访问
}
```

### 6. JSON 序列化

```php
use plugin\theadmin\app\model\Menu;

$menu = Menu::find(1);

// 转换为 JSON（自动应用访问器）
$json = json_encode($menu);

/*
{
    "id": 1,
    "name": "dashboard",
    "title": "仪表盘",
    "keepAlive": true,
    "fixedTab": false,
    "fullPage": false,
    "showBadge": true,
    "linkUrl": "",
    "badgeText": "New",
    "activePath": "/dashboard",
    "createdAt": "2024-01-01 00:00:00",
    "updatedAt": "2024-01-01 00:00:00"
}
*/
```

## 技术实现

### 1. Accessors (访问器)

为每个需要转换的字段定义了访问器方法：

```php
/**
 * keepAlive 访问器 (cache 字段的驼峰式别名)
 */
public function getKeepAliveAttribute(): bool
{
    return (bool)$this->attributes['cache'];
}
```

### 2. Mutators (修改器)

为每个需要转换的字段定义了修改器方法：

```php
/**
 * keepAlive 修改器 (自动映射到 cache 字段)
 */
public function setKeepAliveAttribute(bool $value): void
{
    $this->attributes['cache'] = $value;
}
```

### 3. 隐藏原始字段

在模型中使用 `$hidden` 属性隐藏下划线命名的原始字段：

```php
protected $hidden = [
    'cache',           // 用 keepAlive 代替
    'fixed_tab',       // 用 fixedTab 代替
    'full_page',       // 用 fullPage 代替
    // ...
];
```

### 4. 追加虚拟字段

使用 `$appends` 属性将驼峰命名的字段追加到数组输出：

```php
protected $appends = [
    'keepAlive',
    'fixedTab',
    'fullPage',
    // ...
];
```

### 5. 允许批量赋值

在 `$fillable` 中添加驼峰命名字段：

```php
protected $fillable = [
    // 原有的下划线字段
    'cache',
    'fixed_tab',
    // ...
    
    // 驼峰命名字段（通过修改器自动映射）
    'keepAlive',
    'fixedTab',
    // ...
];
```

## 优势

1. **前后端一致**: 前端使用驼峰命名，后端数据库使用下划线命名，完全自动转换
2. **向后兼容**: 仍然支持使用下划线字段名，不会破坏现有代码
3. **类型安全**: 通过类型提示确保数据类型正确
4. **自动化**: 无需手动转换，Eloquent 自动处理
5. **优雅简洁**: 使用 ORM 原生特性，代码更加优雅

## 注意事项

1. **数据库查询**: 在查询条件中仍需使用下划线字段名
   ```php
   // ✅ 正确
   Menu::where('cache', true)->get();
   
   // ❌ 错误
   Menu::where('keepAlive', true)->get();
   ```

2. **模型属性访问**: 可以使用驼峰命名
   ```php
   $menu = Menu::find(1);
   echo $menu->keepAlive;  // ✅ 正确
   echo $menu->cache;      // ✅ 也可以，但不推荐
   ```

3. **数组输出**: 自动使用驼峰命名
   ```php
   $menu = Menu::find(1);
   $array = $menu->toArray();
   echo $array['keepAlive'];  // ✅ 正确
   echo $array['cache'];      // ❌ 不存在，已被隐藏
   ```

## 迁移建议

如果你的现有代码使用了下划线字段名，可以逐步迁移：

1. **第一阶段**: 保持现有代码不变，系统已支持两种命名方式
2. **第二阶段**: 新代码统一使用驼峰命名
3. **第三阶段**: 逐步重构旧代码，使用驼峰命名

## 总结

通过 Eloquent ORM 的 Accessors 和 Mutators 特性，我们实现了：

- ✅ 数据库层使用 `snake_case` (下划线命名)
- ✅ 应用层使用 `camelCase` (驼峰命名)
- ✅ 自动双向转换
- ✅ 向后兼容
- ✅ 类型安全

这样前端和后端可以统一使用驼峰命名，提升代码的一致性和可维护性。

