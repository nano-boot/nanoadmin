# ThinkPHP 验证器场景方法未设置 only 导致验证所有字段

## 🐛 问题描述

即使：
- ✅ 场景正确设置为 `'store'`
- ✅ `$scene` 数组中 `'store'` 不包含 `'id'`
- ✅ 待验证数据不包含 `'id'`

但仍然报错：`'id require'`

**关键线索**：
1. 改成 `'create'` 场景就不报错
2. 即使移除 `id` 的 `require`，`ids` 也会报错

## 🔍 问题根源

### ThinkPHP 验证器的场景应用机制

在 ThinkPHP 的 `Validate.php` 源码中（`getScene()` 方法）：

```php
protected function getScene(string $scene): void
{
    $method = 'scene' . Str::studly($scene);
    
    if (method_exists($this, $method)) {
        // ✅ 如果存在场景方法（如 sceneStore），调用它
        call_user_func([$this, $method]);
        
    } elseif (isset($this->scene[$scene])) {
        // ❌ 否则，使用 $scene 数组设置 only
        $this->only = $this->scene[$scene];
    }
}
```

**关键点：这是 `if-elseif` 逻辑！**

- 如果存在 `sceneStore()` 方法 → 只调用这个方法
- **不会执行** `$this->only = $this->scene['store']`
- **场景方法必须自己调用 `only()` 来设置验证字段！**

### 问题代码

```php
// MenuValidator.php 第 180-183 行
protected $scene = [
    'store' => [
        'parent_id', 'name', 'path', ...  // ✅ 定义了场景字段
    ],
];

protected function sceneStore()
{
    return $this->append('type', 'checkMenuTypeFields');
    // ❌ 没有调用 only()，所以 $this->scene['store'] 数组被忽略！
}
```

### 为什么改成 create 就不报错？

```php
protected $scene = [
    'store' => [...],    // 有 sceneStore() 方法 → 忽略数组
    'create' => [...],   // 没有 sceneCreate() 方法 → 使用数组 ✅
];
```

因为：
- `'store'` 场景：存在 `sceneStore()` 方法 → 不使用 `$scene['store']` 数组
- `'create'` 场景：不存在 `sceneCreate()` 方法 → 使用 `$scene['create']` 数组 ✅

## 验证流程对比

### 修改前（有 sceneStore 方法但未调用 only）

```
POST /sys/menu → store 场景
  ↓
check() 方法
  ↓
getScene('store')
  ↓
检测到 sceneStore() 方法存在
  ↓
调用 sceneStore()
  ↓
只执行了 append('type', ...)
  ↓
❌ 没有设置 $this->only（场景字段为空）
  ↓
❌ 验证器验证所有规则（包括 id、ids 等）
  ↓
❌ 报错：id require
```

### 修改后（sceneStore 方法中调用 only）

```
POST /sys/menu → store 场景
  ↓
check() 方法
  ↓
getScene('store')
  ↓
检测到 sceneStore() 方法存在
  ↓
调用 sceneStore()
  ↓
执行 only([...]) → 设置场景字段
  ↓
执行 append('type', ...) → 追加自定义规则
  ↓
✅ $this->only = ['parent_id', 'name', ...]
  ↓
✅ 只验证场景中的字段
  ↓
✅ 不验证 id（因为不在 only 列表中）
  ↓
✅ 验证通过
```

### 使用 create 场景（没有 sceneCreate 方法）

```
POST /sys/menu → create 场景
  ↓
check() 方法
  ↓
getScene('create')
  ↓
没有 sceneCreate() 方法
  ↓
执行 else 分支：$this->only = $this->scene['create']
  ↓
✅ $this->only = ['parent_id', 'name', ...]
  ↓
✅ 只验证场景中的字段
  ↓
✅ 验证通过
```

## ✅ 解决方案

### 方案一：在场景方法中调用 only()（推荐）

```php
protected function sceneStore()
{
    // ✅ 先调用 only() 设置场景字段
    return $this->only([
        'parent_id', 'name', 'path', 'component', 'redirect', 'title',
        'icon', 'type', 'permission', 'hidden', 'hide_tab', 'full_page',
        'keep_alive', 'fixed_tab', 'link_url', 'iframe', 'show_badge',
        'badge_text', 'active_path', 'status', 'sort'
    ])
    // ✅ 再调用 append() 追加自定义规则
    ->append('type', 'checkMenuTypeFields');
}

protected function sceneUpdate()
{
    return $this->only([
        'id', 'parent_id', 'name', 'path', 'component', 'redirect', 'title', 
        'icon', 'type', 'permission', 'hidden', 'hide_tab', 'full_page', 
        'keep_alive', 'fixed_tab', 'link_url', 'iframe', 'show_badge', 
        'badge_text', 'active_path', 'status', 'sort'
    ])->append('type', 'checkMenuTypeFields');
}
```

### 方案二：删除场景方法，只使用数组定义

如果场景方法只是为了追加简单规则，可以考虑删除场景方法：

```php
protected $scene = [
    'store' => [
        'parent_id', 'name', 'path', 'component', 'redirect', 'title',
        'icon', 'type', 'permission', 'hidden', 'hide_tab', 'full_page',
        'keep_alive', 'fixed_tab', 'link_url', 'iframe', 'show_badge',
        'badge_text', 'active_path', 'status', 'sort'
    ],
];

// ❌ 删除 sceneStore() 方法
// protected function sceneStore() { ... }
```

然后在验证后手动执行自定义验证：

```php
// 控制器中
$validator = new MenuValidator();
// 验证通过后，手动执行类型检查
if ($data['type']) {
    // 执行类型相关的验证逻辑
}
```

### 方案三：场景方法中使用 scene 数组

```php
protected function sceneStore()
{
    // ✅ 使用 $this->scene['store'] 数组
    return $this->only($this->scene['store'])
                ->append('type', 'checkMenuTypeFields');
}
```

## 📊 对比总结

| 方案 | 优点 | 缺点 |
|-----|------|------|
| **方案一** | ✅ 清晰明确<br>✅ 支持自定义规则 | ❌ 代码重复（字段列表写两次） |
| **方案二** | ✅ 简洁<br>✅ 字段只定义一次 | ❌ 不支持场景级自定义规则 |
| **方案三** | ✅ 字段只定义一次<br>✅ 支持自定义规则 | ✅ 最佳方案！ |

## 🎯 最佳实践

### ✅ 推荐做法（方案三）

```php
class MenuValidator extends ValidatorBase
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'name' => 'require|string|min:2|max:100',
        // ...
    ];

    protected $scene = [
        'store' => ['parent_id', 'name', 'path', ...],
        'update' => ['id', 'parent_id', 'name', ...],
        'show' => ['id'],
        'destroy' => ['id'],
    ];

    /**
     * store 场景：使用 $scene 数组 + 自定义规则
     */
    protected function sceneStore()
    {
        return $this->only($this->scene['store'])
                    ->append('type', 'checkMenuTypeFields');
    }

    /**
     * update 场景：使用 $scene 数组 + 自定义规则
     */
    protected function sceneUpdate()
    {
        return $this->only($this->scene['update'])
                    ->append('type', 'checkMenuTypeFields');
    }
    
    // show、destroy 场景没有自定义规则，不需要定义方法
}
```

### ❌ 错误做法

```php
// ❌ 错误1：有场景方法但不调用 only
protected function sceneStore()
{
    return $this->append('type', 'checkMenuTypeFields');
    // 导致不使用 $scene['store'] 数组，验证所有字段
}

// ❌ 错误2：场景方法和数组重复定义字段
protected $scene = [
    'store' => ['name', 'title'],  // 定义一次
];

protected function sceneStore()
{
    return $this->only(['name', 'title'])  // 又定义一次（重复）
                ->append(...);
}
```

## 📝 修复步骤

### 1. 修改 MenuValidator.php

```php
// 第 180-188 行
protected function sceneStore()
{
    return $this->only($this->scene['store'])
                ->append('type', 'checkMenuTypeFields');
}

// 第 193-201 行
protected function sceneUpdate()
{
    return $this->only($this->scene['update'])
                ->append('type', 'checkMenuTypeFields');
}
```

### 2. 测试验证

```bash
# 测试创建菜单（应该成功）
curl -X POST http://your-domain/sys/menu \
  -H "Content-Type: application/json" \
  -d '{
    "name": "testMenu",
    "title": "测试菜单",
    "type": "M",
    "path": "/test",
    "component": "TestComponent"
  }'
```

**预期结果**：
- ✅ 不再报 `id require` 错误
- ✅ 不再报 `ids require` 错误
- ✅ 创建成功

## 🎓 ThinkPHP 验证器场景机制总结

### 关键规则

1. **如果定义了场景方法**（如 `sceneStore()`）
   - 调用场景方法
   - **不会使用** `$scene` 数组
   - **场景方法必须调用 `only()` 来设置验证字段**

2. **如果没有定义场景方法**
   - 使用 `$scene` 数组
   - 自动设置 `$this->only`
   - 只验证数组中的字段

3. **only() 方法**
   - 设置要验证的字段列表
   - 只有在 `$this->only` 中的字段才会被验证

4. **append() 方法**
   - 追加验证规则
   - 不影响字段列表

### 最佳实践

| 场景 | 是否有自定义规则 | 推荐方案 |
|-----|---------------|---------|
| 简单场景 | ❌ 无 | 只用 `$scene` 数组，不定义场景方法 |
| 复杂场景 | ✅ 有 | 场景方法中使用 `only($this->scene[...])` |

## 🎉 总结

问题的根本原因：
- ❌ 定义了 `sceneStore()` 方法但没有调用 `only()`
- ❌ 导致 `$scene['store']` 数组被忽略
- ❌ 验证器验证所有规则

解决方案：
- ✅ 在场景方法中调用 `only($this->scene['store'])`
- ✅ 保持字段定义在 `$scene` 数组中（单一数据源）
- ✅ 使用场景方法追加自定义规则

现在你的验证器会正确工作了！🎊
