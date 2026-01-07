# TheAdmin 菜单初始化脚本使用指南

## 概述

本目录包含TheAdmin系统的菜单初始化和维护脚本，用于将前端路由配置同步到数据库中。

## 文件说明

### 1. menu_init_data.sql
- **用途**: 初始化菜单数据的主脚本
- **功能**: 
  - 插入基础菜单数据
  - 创建默认角色和管理员账户
  - 设置角色菜单权限关联
  - 数据完整性验证

### 2. menu_update_helper.sql
- **用途**: 菜单数据维护和更新辅助脚本
- **功能**:
  - 清理无效数据
  - 修复菜单排序
  - 批量更新菜单属性
  - 权限管理工具

### 3. README_MENU_INIT.md
- **用途**: 使用说明文档

## 使用步骤

### 首次初始化

1. **确保数据库表结构最新**
   ```sql
   -- 执行主安装脚本
   SOURCE theadmin-service/plugin/theadmin/sql/install.sql;
   ```

2. **备份现有数据（如有）**
   ```sql
   -- 备份菜单数据
   CREATE TABLE th_sys_menu_backup AS SELECT * FROM th_sys_menu;
   ```

3. **执行初始化脚本**
   ```sql
   SOURCE theadmin-service/plugin/theadmin/sql/menu_init_data.sql;
   ```

4. **验证数据**
   - 脚本执行后会自动显示验证结果
   - 检查菜单层级结构是否正确
   - 确认角色权限分配是否合理

### 更新现有数据

1. **使用维护脚本**
   ```sql
   SOURCE theadmin-service/plugin/theadmin/sql/menu_update_helper.sql;
   ```

2. **根据需要执行特定维护操作**
   - 参考脚本中的注释示例
   - 根据实际需求修改相应部分

## 数据结构说明

### 菜单类型 (type)
- `D`: 目录 - 包含子菜单的父级菜单
- `M`: 菜单 - 具体的页面菜单
- `B`: 按钮 - 页面内的权限按钮
- `L`: 外链 - 外部链接
- `I`: 内嵌 - 内嵌页面

### 角色权限 (roles)
- `R_SUPER`: 超级管理员 - 拥有所有权限
- `R_ADMIN`: 管理员 - 拥有大部分权限
- `R_USER`: 普通用户 - 拥有基础权限

### 菜单属性
- `hidden`: 是否隐藏菜单
- `cacheable`: 是否缓存页面
- `affix`: 是否固定标签页
- `full_page`: 是否全屏显示

## 前端路由配置对应关系

| 前端配置 | 数据库字段 | 说明 |
|---------|-----------|------|
| `name` | `name` | 路由名称 |
| `path` | `path` | 路由路径 |
| `component` | `component` | 组件路径 |
| `meta.title` | `title` | 菜单标题 |
| `meta.icon` | `icon` | 菜单图标 |
| `meta.roles` | `roles` | 角色权限 |
| `meta.isHide` | `hidden` | 是否隐藏 |
| `meta.keepAlive` | `cacheable` | 是否缓存 |
| `meta.fixedTab` | `affix` | 是否固定标签 |
| `meta.authList` | `auth_list` | 权限按钮列表 |

## 注意事项

### 执行前检查
1. 确保数据库连接正常
2. 确认表结构已更新到最新版本
3. 备份重要数据

### 数据一致性
1. 菜单ID使用自增，脚本中的ID仅用于维护父子关系
2. 组件路径必须与前端路由别名一致
3. 角色权限数组使用JSON格式存储

### 权限配置
1. 超级管理员默认拥有所有菜单权限
2. 管理员权限可根据需要调整
3. 新增角色需要手动配置菜单权限

## 故障排除

### 常见问题

1. **菜单层级错误**
   - 检查parent_id是否正确
   - 确认父菜单是否存在

2. **组件路径错误**
   - 对比前端routesAlias.ts配置
   - 确认路径格式正确

3. **权限配置问题**
   - 检查roles字段JSON格式
   - 确认角色代码是否存在

4. **数据重复插入**
   - 使用INSERT IGNORE避免重复
   - 检查唯一约束字段

### 验证命令

```sql
-- 检查菜单数据
SELECT COUNT(*) FROM th_sys_menu WHERE deleted = FALSE;

-- 检查角色权限
SELECT r.name, COUNT(rm.menu_id) 
FROM th_sys_role r 
LEFT JOIN th_sys_role_menu rm ON r.id = rm.role_id 
GROUP BY r.id;

-- 检查数据完整性
SELECT * FROM th_sys_menu 
WHERE parent_id > 0 
  AND parent_id NOT IN (SELECT id FROM th_sys_menu WHERE parent_id = 0);
```

## 维护建议

1. **定期同步**: 前端路由配置变更后及时同步数据库
2. **权限审计**: 定期检查角色权限配置是否合理
3. **数据备份**: 重要操作前备份相关数据
4. **版本控制**: 记录每次数据变更的版本和原因

## 联系支持

如遇到问题，请检查：
1. 数据库表结构是否最新
2. 前端路由配置是否有变更
3. 脚本执行日志中的错误信息

更多技术支持请参考项目文档或联系开发团队。