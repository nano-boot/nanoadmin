-- 重新设计 fe_menus 表结构
-- 基于前端路由结构和菜单管理页面需求
-- 创建时间: 2025-01-02
-- 菜单类型: M: 菜单, B: 按钮, I: 外链

-- 清空菜单表
-- DELETE FROM `th_sys_menu`;

-- 插入默认菜单数据，匹配前端路由结构
INSERT INTO `th_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `icon`, `redirect`,
    `parent_id`, `sort`, `type`, `status`,
    `hidden`, `hide_tab`, `cache`, `fixed_tab`, `active_path`
) VALUES
-- === 一级菜单 ===
(1, 'Dashboard', '数据面板', '/dashboard', '/index/index', '&#xe721;', '',
 0,  1, 'M', 1, 0, 0, 1, 0,  ''),

(2, 'Auth', '权限管理', '/permission', '/index/index', '&#xe7ad;', '',
 0,  2, 'M', 1, 0, 0, 1, 0,  ''),

(3, 'System', '系统管理', '/system', '/index/index', '&#xe7b8;', '',
 0,  3, 'M', 1, 0, 0, 1, 0, ''),


-- === 二级菜单 - Dashboard ===
(100, 'Console', '控制台', '/dashboard/console', '/dashboard/console', '', '',
 1,  1, 'M', 1, 0, 0, 0, 1,  ''),

-- === 二级菜单 - Auth ===
(200, 'AuthUser', '用户管理', '/permission/user', '/permission/user', '', '',
 2,  1, 'M', 1, 0, 0, 1, 0,  ''),

(201, 'AuthRole', '角色管理', '/permission/role', '/permission/role', '', '',
 2,  2, 'M', 1, 0, 0, 1, 0, ''),

(202, 'AuthMenu', '菜单管理', '/permission/menu', '/permission/menu', '', '',
 2,  3, 'M', 1, 1, 0, 0, 0,  '/permission/user'),


-- === 二级菜单 - System ===
(300, 'SystemFile', '文件管理', '/system/file', '/system/file', '', '',
 3,  1, 'M', 1, 0, 0, 1, 0, ''),

(301, 'SystemDict', '字典管理 ', '/system/dict', '/system/dict', '', '',
 3, 1, 'M', 1, 0, 0, 1, 0,  ''),

(302, 'SystemConfig', '配置管理', '/system/config', '/system/config', '', '',
 3, 2, 'M', 1, 0, 0, 1, 0,  ''),

(303, 'SystemLog', '日志管理', '/system/log', '/system/log', '', '',
 3, 3, 'M', 1, 0, 0, 1, 0,  '');



-- === 按钮权限 ===
INSERT INTO `th_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `parent_id`,
    `sort`, `type`, `status`, `hidden`, `active_path`
) VALUES
-- 用户管理按钮权限
(2001, 'AuthUserCreate', '创建用户', '/permission/user/create', '', 200,  1, 'B', 1, 1, ''),
(2002, 'AuthUserEdit', '编辑用户', '/permission/user/edit', '', 200,  2, 'B', 1, 1, ''),
(2003, 'AuthUserDelete', '删除用户', '/permission/user/delete', '', 200,  3, 'B', 1, 1, ''),
(2004, 'AuthUserResetPwd', '重置密码', '/permission/user/reset-pwd', '', 200,  4, 'B', 1, 1, ''),

-- 角色管理按钮权限
(2011, 'AuthRoleCreate', '创建角色', '/permission/role/create', '', 201,  1, 'B', 1, 1, ''),
(2012, 'AuthRoleEdit', '编辑角色', '/permission/role/edit', '', 201,  2, 'B', 1, 1, ''),
(2013, 'AuthRoleDelete', '删除角色', '/permission/role/delete', '', 201,  3, 'B', 1, 1, ''),
(2014, 'AuthRolePermission', '分配权限', '/permission/role/permission', '', 201,  4, 'B', 1, 1, ''),


-- 菜单管理按钮权限
(2021, 'AuthMenuCreate', '创建菜单', '/permission/menu/create', '', 202,  1, 'B', 1, 1, ''),
(2022, 'AuthMenuEdit', '编辑菜单', '/permission/menu/edit', '', 202,  2, 'B', 1, 1, ''),
(2023, 'AuthMenuDelete', '删除菜单', '/permission/menu/delete', '', 202,  3, 'B', 1, 1, ''),
(2024, 'AuthMenuSort', '菜单排序', '/permission/menu/sort', '', 202,  4, 'B', 1, 1, ''),

-- 文件管理按钮权限
(3001, 'SystemFileCreate', '创建文件', '/system/file/create', '', 300,  1, 'B', 1, 1, ''),
(3002, 'SystemFileEdit', '编辑文件', '/system/file/edit', '', 300,  2, 'B', 1, 1, ''),
(3003, 'SystemFileDelete', '删除文件', '/system/file/delete', '', 300,  3, 'B', 1, 1, ''),

-- 字典管理按钮权限
(3011, 'SystemDictCreate', '创建字典', '/system/dict/create', '', 301,  1, 'B', 1, 1, ''),
(3012, 'SystemDictEdit', '编辑字典', '/system/dict/edit', '', 301,  2, 'B', 1, 1, ''),
(3013, 'SystemDictDelete', '删除字典', '/system/dict/delete', '', 301,  3, 'B', 1, 1, ''),


-- 配置管理按钮权限
(3021, 'SystemConfigCreate', '创建配置', '/system/config/create', '', 302,  1, 'B', 1, 1, ''),
(3022, 'SystemConfigEdit', '编辑配置', '/system/config/edit', '', 302,  2, 'B', 1, 1, ''),
(3023, 'SystemConfigDelete', '删除配置', '/system/config/delete', '', 302,  3, 'B', 1, 1, ''),


-- 日志管理按钮权限
(3031, 'SystemLogCreate', '创建日志', '/system/log/create', '', 303,  1, 'B', 1, 1, ''),
(3032, 'SystemLogEdit', '编辑日志', '/system/log/edit', '', 303,  2, 'B', 1, 1, ''),
(3033, 'SystemLogDelete', '删除日志', '/system/log/delete', '', 303,  3, 'B', 1, 1, '');




-- 重置自增ID
ALTER TABLE `th_sys_menu` AUTO_INCREMENT = 8000;


-- 表结构说明
/*
新表结构特点（完整路径版本）：

🔧 路径设计：
- 使用完整路径，如 /auth/user, /auth/role, /auth/menu, /system/file, /system/dict, /system/config, /system/log
- path 字段保持全局唯一约束，因为每个路径都是唯一的
- 完全匹配前端 RoutesAlias 中定义的路径

1. 基础字段：
   - name: 路由名称，对应前端 AppRouteRecord.name（全局唯一）
   - title: 显示标题，对应前端 meta.title
   - path: 路由路径，对应前端 AppRouteRecord.path（完整路径）
   - component: 组件路径，对应前端 AppRouteRecord.component

2. 元数据字段：
   - icon: 图标，对应前端 meta.icon
   - redirect: 重定向，对应前端 AppRouteRecord.redirect
   - roles: 角色权限，对应前端 meta.roles（JSON数组）
   - auth_list: 操作权限，对应前端 meta.authList（JSON数组）

3. 显示控制：
   - is_hidden: 对应前端 meta.isHide
   - is_hide_tab: 对应前端 meta.isHideTab
   - is_full_page: 对应前端 meta.isFullPage
   - keep_alive: 对应前端 meta.keepAlive
   - fixed_tab: 对应前端 meta.fixedTab

4. 外链支持：
   - external_link: 对应前端 meta.link
   - is_iframe: 对应前端 meta.isIframe

5. 扩展字段：
   - active_path: 对应前端 meta.activePath
   - badge_text: 对应前端 meta.showTextBadge
   - show_badge: 对应前端 meta.showBadge

6. 层级管理：
   - parent_id: 父菜单ID
   - level: 菜单层级
   - sort_order: 排序权重

7. 类型支持：
   - menu: 普通菜单
   - button: 按钮权限
   - iframe: 外链菜单   

路径映射示例：
✅ 一级菜单：/dashboard, /auth, /system
✅ 二级菜单：/dashboard/console, /auth/user, /auth/role, /auth/menu, /system/file, /system/dict, /system/config, /system/log


这个设计完全匹配前端路由结构，支持：
- 全局唯一的路径（path字段唯一约束）
- 清晰的层级关系（parent_id + level）
- 完整的权限控制（roles字段）
*/


