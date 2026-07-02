-- =====================================================
-- 重新设计 fe_menus 表结构
-- 基于前端路由结构和菜单管理页面需求
-- 创建时间: 2025-01-02
-- 菜单类型: D: 目录, M: 菜单, B: 按钮, L: 外链（新窗口打开完整 URL）, I: 内嵌（iframe 嵌入页面）
-- =====================================================
--
-- P0-1 按钮节点强规则（必须遵循）：
-- 1. 按钮节点（type='B'）必须设置 permission，且必须挂在父级菜单页面（type='M'）下
-- 2. 页面节点（type='M' 或 'D'）不设置 permission（permission 为空）
-- 3. 同级按钮节点 permission 不可重复
-- 4. permission 值应与 na_sys_permission.code 保持同一值域（如 sys:admin:create）
--
-- =====================================================

-- 清空菜单表
-- DELETE FROM `na_sys_menu`;

-- 插入默认菜单数据，匹配前端路由结构
-- 字段顺序: id, name, title, path, component, icon, redirect, permission,
--           parent_id, sort, type, status, hide, hide_tab, cache, fixed_tab, active_path, deleted
-- 字段顺序: id, name(中文标题), path, component, icon, redirect, permission,
--           parent_id, sort, type, status, hide, hide_tab, cache, fixed_tab, active_path, deleted
-- 注意：na_sys_menu 表没有独立的 title 列，菜单标题直接存在 name 字段
REPLACE INTO `na_sys_menu` (
    `id`, `name`, `path`, `component`, `icon`, `redirect`, `permission`,
    `parent_id`, `sort`, `type`, `status`,
    `hide`, `hide_tab`, `cache`, `fixed_tab`, `active_path`, `deleted`
) VALUES
-- === 一级菜单
(1, '数据面板', '/dashboard', '', 'ri:pie-chart-line', '', '',
 0,  100, 'D', 1, 0, 0, 1, 0,  '', 0),
(2, '权限管理', '/permission', '', 'ri:fingerprint-line', '', '',
 0,  100, 'D', 1, 0, 0, 1, 0,  '', 0),
(3, '系统管理', '/system', '', 'ri:settings-line', '', '',
 0,  100, 'D', 1, 0, 0, 1, 0, '', 0),
(4, '接口管理', '/interface', '', 'ri:code-view', '', '',
 0,  100, 'D', 1, 0, 0, 1, 0,  '', 0),

-- === 二级页面（M 类型，permission 为空，path 只写自己那一段，由 buildFullPath 自动拼接父路径）===
(100, '控制台', 'console', '/dashboard/console', 'ri:dashboard-2-line', '', '',
 1,  100, 'M', 1, 0, 0, 0, 1,  '', 0),
(200, '用户管理', 'user', '/permission/user', 'ri:user-2-line', '', '',
 2,  100, 'M', 1, 0, 0, 1, 0,  '', 0),
(201, '角色管理', 'role', '/permission/role', 'ri:user-settings-line', '', '',
 2,  100, 'M', 1, 0, 0, 1, 0, '', 0),
(202, '菜单管理', 'menu', '/permission/menu', 'ri:menu-line', '', '',
 2,  100, 'M', 1, 0, 0, 0, 0,  'user', 0),
(300, '文件管理', 'file', '/system/file', 'ri:file-2-line', '', '',
 3,  100, 'M', 1, 0, 0, 1, 0, '', 0),
(301, '字典管理', 'dict-type', '/system/dict-type', 'ri:book-2-line', '', '',
 3, 1, 'M', 1, 0, 0, 1, 0,  '', 0),
(302, '配置管理', 'config', '/system/config', 'ri:settings-2-line', '', '',
 3, 2, 'M', 1, 0, 0, 1, 0,  '', 0),
(303, '日志管理', 'log', '', 'ri:file-text-line', '', '',
 3, 3, 'D', 1, 0, 0, 1, 0,  '', 0),
(304, '登录日志', 'login', '/system/log/login', 'ri:user-follow-line', '', '',
 303, 1, 'M', 1, 0, 0, 1, 0,  '', 0),
(305, '操作日志', 'operation', '/system/log/operation', 'ri:history-line', '', '',
 303, 2, 'M', 1, 0, 0, 1, 0,  '', 0),
(400, 'swagger文档', 'swagger', '', 'ri:file-code-line', '', '',
 4,  100, 'I', 1, 0, 0, 1, 0,  'swagger', 0);

-- =====================================================
-- 按钮权限节点（B 类型，permission 必填）
-- 规则：挂在父级菜单页面（M 类型）下，permission 与 na_sys_permission.code 保持同一值域
-- =====================================================
-- 按钮节点的 name 存中文按钮名；permission 字段同时承担动作权限标识
REPLACE INTO `na_sys_menu` (
    `id`, `name`, `path`, `component`, `permission`, `parent_id`,
    `sort`, `type`, `status`, `hide`, `deleted`
) VALUES
-- 用户管理按钮（parent_id=200 -> 用户管理，path 只写自己那一段）
(2000, '用户列表', 'list',      '', 'sys:admin:page',           200,  100, 'B', 1, 1, 0),
(2001, '创建用户', 'create',    '', 'sys:admin:create',         200,  100, 'B', 1, 1, 0),
(2002, '编辑用户', 'edit',     '', 'sys:admin:update',         200,  100, 'B', 1, 1, 0),
(2003, '删除用户', 'delete',   '', 'sys:admin:delete',         200,  100, 'B', 1, 1, 0),
(2004, '重置密码', 'reset-pwd', '', 'sys:admin:assign-role',    200,  100, 'B', 1, 1, 0),

-- 角色管理按钮（parent_id=201 -> 角色管理，path 只写自己那一段）
(2010, '角色列表', 'list',       '', 'sys:role:page',             201,  100, 'B', 1, 1, 0),
(2011, '创建角色', 'create',    '', 'sys:role:create',           201,  100, 'B', 1, 1, 0),
(2012, '编辑角色', 'edit',      '', 'sys:role:update',           201,  100, 'B', 1, 1, 0),
(2013, '删除角色', 'delete',    '', 'sys:role:delete',           201,  100, 'B', 1, 1, 0),
(2014, '分配权限', 'permission', '', 'sys:role:assign-permission', 201,  100, 'B', 1, 1, 0),

-- 菜单管理按钮（parent_id=202 -> 菜单管理，path 只写自己那一段）
(2020, '菜单列表', 'list',       '', 'sys:menu:page',             202,  100, 'B', 1, 1, 0),
(2021, '创建菜单', 'create',    '', 'sys:menu:create',           202,  100, 'B', 1, 1, 0),
(2022, '编辑菜单', 'edit',      '', 'sys:menu:update',           202,  100, 'B', 1, 1, 0),
(2023, '删除菜单', 'delete',    '', 'sys:menu:delete',           202,  100, 'B', 1, 1, 0),
(2024, '菜单排序', 'sort',     '', 'sys:menu:sort',             202,  100, 'B', 1, 1, 0),

-- 文件管理按钮（parent_id=300 -> 文件管理，path 只写自己那一段）
(3001, '文件列表', 'list',   '', 'sys:file:list',   300,  100, 'B', 1, 1, 0),
(3002, '创建文件', 'create', '', 'sys:file:create', 300,  100, 'B', 1, 1, 0),
(3003, '编辑文件', 'edit',   '', 'sys:file:update', 300,  100, 'B', 1, 1, 0),
(3004, '删除文件', 'delete', '', 'sys:file:delete', 300,  100, 'B', 1, 1, 0),

-- 字典管理按钮（parent_id=301 -> 字典管理，path 只写自己那一段）
(3010, '字典列表', 'list',   '', 'sys:dict:type:page',   301,  100, 'B', 1, 1, 0),
(3011, '创建字典', 'create', '', 'sys:dict:type:create', 301,  100, 'B', 1, 1, 0),
(3012, '编辑字典', 'edit',   '', 'sys:dict:type:update', 301,  100, 'B', 1, 1, 0),
(3013, '删除字典', 'delete', '', 'sys:dict:type:delete', 301,  100, 'B', 1, 1, 0),

-- 配置管理按钮（parent_id=302 -> 配置管理，path 只写自己那一段）
(3020, '配置列表', 'list',   '', 'sys:config:page',   302,  100, 'B', 1, 1, 0),
(3021, '创建配置', 'create', '', 'sys:config:create', 302,  100, 'B', 1, 1, 0),
(3022, '编辑配置', 'edit',   '', 'sys:config:update', 302,  100, 'B', 1, 1, 0),
(3023, '删除配置', 'delete', '', 'sys:config:delete', 302,  100, 'B', 1, 1, 0),

-- 日志管理按钮（parent_id=303 -> 日志管理，path 只写自己那一段）
(3040, '登录日志列表', 'list',   '', 'sys:log:login:page',   304,  100, 'B', 1, 1, 0),
(3041, '删除登录日志', 'delete', '', 'sys:log:login:delete', 304,  100, 'B', 1, 1, 0),
(3050, '操作日志列表', 'list',   '', 'sys:log:operation:page',   305,  100, 'B', 1, 1, 0),
(3051, '删除操作日志', 'delete', '', 'sys:log:operation:delete', 305,  100, 'B', 1, 1, 0);

-- =====================================================
-- swagger 文档菜单特殊配置（link 字段不在上方批量插入的列定义中，单独 UPDATE）
-- swagger 菜单的 path 已改为相对路径 'swagger'，active_path 也对应调整
-- =====================================================
UPDATE `na_sys_menu`
   SET `link`   = 'http://localhost:8787/sys/openapi',
       `iframe` = 1
 WHERE `id` = 400;

-- 重置自增ID
ALTER TABLE `na_sys_menu` AUTO_INCREMENT = 8000;

-- =====================================================
-- 表结构说明
-- =====================================================

-- 菜单类型说明：
-- D（Directory）: 目录 - 纯容器，不挂组件
-- M（Menu）: 菜单页面 - 对应具体功能页面
-- B（Button）: 按钮权限 - 用于细粒度权限控制，必须挂在 M 类型下
-- L（Link）: 外链
-- I（Iframe）: 内嵌页面

-- =====================================================
-- P0-1 按钮节点强规则（所有 SQL 和业务代码必须遵循）
-- =====================================================
-- 1. 按钮节点（type='B'）必须设置 permission，且必须挂在父级菜单页面（type='M'）下
-- 2. 页面节点（type='M' 或 'D'）不设置 permission（permission 为空字符串）
-- 3. 同级按钮节点 permission 不可重复
-- 4. permission 值应与 na_sys_permission.code 保持同一值域（如 sys:admin:create）
-- 5. 不允许按钮节点挂在目录（D）或外链（L/I）类型节点下
