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
REPLACE INTO `na_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `icon`, `redirect`, `permission`,
    `parent_id`, `sort`, `type`, `status`,
    `hide`, `hide_tab`, `cache`, `fixed_tab`, `active_path`, `deleted`
) VALUES
-- === 一级菜单（M 类型，permission 为空）===
(1, 'Dashboard', '数据面板', '/dashboard', '/index/index', 'ri:pie-chart-line', '', '',
 0,  1, 'M', 1, 0, 0, 1, 0,  '', 0),
(2, 'Permission', '权限管理', '/permission', '/index/index', 'ri:fingerprint-line', '', '',
 0,  2, 'M', 1, 0, 0, 1, 0,  '', 0),
(3, 'System', '系统管理', '/system', '/index/index', 'ri:settings-line', '', '',
 0,  3, 'M', 1, 0, 0, 1, 0, '', 0),
(4, 'InterfaceManage', '接口管理', '/interface', '', 'ri:code-view', '', '',
 0,  4, 'M', 1, 0, 0, 1, 0,  '', 0),

-- === 二级页面（M 类型，permission 为空）===
(100, 'Console', '控制台', '/dashboard/console', '/dashboard/console', 'ri:dashboard-2-line', '', '',
 1,  1, 'M', 1, 0, 0, 0, 1,  '', 0),
(200, 'AuthUser', '用户管理', '/permission/user', '/permission/user', 'ri:user-2-line', '', '',
 2,  1, 'M', 1, 0, 0, 1, 0,  '', 0),
(201, 'AuthRole', '角色管理', '/permission/role', '/permission/role', 'ri:user-settings-line', '', '',
 2,  2, 'M', 1, 0, 0, 1, 0, '', 0),
(202, 'AuthMenu', '菜单管理', '/permission/menu', '/permission/menu', 'ri:menu-line', '', '',
 2,  3, 'M', 1, 1, 0, 0, 0,  '/permission/user', 0),
(300, 'SystemFile', '文件管理', '/system/file', '/system/file', 'ri:file-2-line', '', '',
 3,  1, 'M', 1, 0, 0, 1, 0, '', 0),
(301, 'SystemDictType', '字典管理', '/system/dict', '/system/dict-type', 'ri:book-2-line', '', '',
 3, 1, 'M', 1, 0, 0, 1, 0,  '', 0),
(302, 'SystemConfig', '配置管理', '/system/config', '/system/config', 'ri:settings-2-line', '', '',
 3, 2, 'M', 1, 0, 0, 1, 0,  '', 0),
(303, 'SystemLog', '日志管理', '/system/log', '/system/log', 'ri:file-text-line', '', '',
 3, 3, 'M', 1, 0, 0, 1, 0,  '', 0),
(304, 'SystemLoginLog', '登录日志', '/system/login-log', '/system/login-log', 'ri:user-follow-line', '', '',
 303, 1, 'M', 1, 0, 0, 1, 0,  '', 0),
(305, 'SystemOperationLog', '操作日志', '/system/operation-log', '/system/operation-log', 'ri:history-line', '', '',
 303, 2, 'M', 1, 0, 0, 1, 0,  '', 0),
(400, 'SwaggerDoc', 'swagger文档', '/interface/swagger', '', 'ri:file-code-line', '', '',
 4,  1, 'I', 1, 0, 0, 1, 0,  '/interface/swagger', 0);

-- =====================================================
-- 按钮权限节点（B 类型，permission 必填）
-- 规则：挂在父级菜单页面（M 类型）下，permission 与 na_sys_permission.code 保持同一值域
-- =====================================================
REPLACE INTO `na_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `permission`, `parent_id`,
    `sort`, `type`, `status`, `hide`, `deleted`
) VALUES
-- 用户管理按钮（parent_id=200 -> AuthUser）
(2000, 'AuthUserList',    '用户列表',   '/permission/user/list',      '', 'sys:admin:page',           200,  1, 'B', 1, 1, 0),
(2001, 'AuthUserCreate',  '创建用户',   '/permission/user/create',    '', 'sys:admin:create',         200,  2, 'B', 1, 1, 0),
(2002, 'AuthUserEdit',    '编辑用户',   '/permission/user/edit',     '', 'sys:admin:update',         200,  3, 'B', 1, 1, 0),
(2003, 'AuthUserDelete',  '删除用户',   '/permission/user/delete',   '', 'sys:admin:delete',         200,  4, 'B', 1, 1, 0),
(2004, 'AuthUserResetPwd','重置密码',   '/permission/user/reset-pwd', '', 'sys:admin:assign-role',    200,  5, 'B', 1, 1, 0),

-- 角色管理按钮（parent_id=201 -> AuthRole）
(2010, 'AuthRoleList',    '角色列表',   '/permission/role/list',       '', 'sys:role:page',             201,  1, 'B', 1, 1, 0),
(2011, 'AuthRoleCreate',  '创建角色',   '/permission/role/create',    '', 'sys:role:create',           201,  2, 'B', 1, 1, 0),
(2012, 'AuthRoleEdit',    '编辑角色',   '/permission/role/edit',      '', 'sys:role:update',           201,  3, 'B', 1, 1, 0),
(2013, 'AuthRoleDelete',  '删除角色',   '/permission/role/delete',    '', 'sys:role:delete',           201,  4, 'B', 1, 1, 0),
(2014, 'AuthRolePermission','分配权限',  '/permission/role/permission', '', 'sys:role:assign-permission', 201,  5, 'B', 1, 1, 0),

-- 菜单管理按钮（parent_id=202 -> AuthMenu）
(2020, 'AuthMenuList',   '菜单列表',   '/permission/menu/list',       '', 'sys:menu:page',             202,  1, 'B', 1, 1, 0),
(2021, 'AuthMenuCreate',  '创建菜单',   '/permission/menu/create',    '', 'sys:menu:create',           202,  2, 'B', 1, 1, 0),
(2022, 'AuthMenuEdit',    '编辑菜单',   '/permission/menu/edit',      '', 'sys:menu:update',           202,  3, 'B', 1, 1, 0),
(2023, 'AuthMenuDelete',  '删除菜单',   '/permission/menu/delete',    '', 'sys:menu:delete',           202,  4, 'B', 1, 1, 0),
(2024, 'AuthMenuSort',    '菜单排序',   '/permission/menu/sort',     '', 'sys:menu:sort',             202,  5, 'B', 1, 1, 0),

-- 文件管理按钮（parent_id=300 -> SystemFile）
(3001, 'SystemFileList',   '文件列表',   '/system/file/list',   '', 'sys:file:list',   300,  1, 'B', 1, 1, 0),
(3002, 'SystemFileCreate', '创建文件',   '/system/file/create', '', 'sys:file:create', 300,  2, 'B', 1, 1, 0),
(3003, 'SystemFileEdit',   '编辑文件',   '/system/file/edit',   '', 'sys:file:update', 300,  3, 'B', 1, 1, 0),
(3004, 'SystemFileDelete', '删除文件',   '/system/file/delete', '', 'sys:file:delete', 300,  4, 'B', 1, 1, 0),

-- 字典管理按钮（parent_id=301 -> SystemDictType）
(3010, 'SystemDictTypeList',  '字典列表',   '/system/dict-type/list',   '', 'sys:dict:type:page',   301,  1, 'B', 1, 1, 0),
(3011, 'SystemDictTypeCreate','创建字典',   '/system/dict-type/create', '', 'sys:dict:type:create', 301,  2, 'B', 1, 1, 0),
(3012, 'SystemDictEdit',      '编辑字典',   '/system/dict-type/edit',   '', 'sys:dict:type:update', 301,  3, 'B', 1, 1, 0),
(3013, 'SystemDictDelete',    '删除字典',   '/system/dict-type/delete', '', 'sys:dict:type:delete', 301,  4, 'B', 1, 1, 0),

-- 配置管理按钮（parent_id=302 -> SystemConfig）
(3020, 'SystemConfigList',   '配置列表',   '/system/config/list',   '', 'sys:config:page',   302,  1, 'B', 1, 1, 0),
(3021, 'SystemConfigCreate', '创建配置',   '/system/config/create', '', 'sys:config:create', 302,  2, 'B', 1, 1, 0),
(3022, 'SystemConfigEdit',   '编辑配置',   '/system/config/edit',   '', 'sys:config:update', 302,  3, 'B', 1, 1, 0),
(3023, 'SystemConfigDelete', '删除配置',   '/system/config/delete', '', 'sys:config:delete', 302,  4, 'B', 1, 1, 0),

-- 日志管理按钮（parent_id=303 -> SystemLog）
(3030, 'SystemLogList',   '日志列表',   '/system/log/list',   '', 'sys:log:page',   303,  1, 'B', 1, 1, 0),
(3031, 'SystemLogCreate', '创建日志',   '/system/log/create', '', 'sys:log:create', 303,  2, 'B', 1, 1, 0),
(3032, 'SystemLogEdit',   '编辑日志',   '/system/log/edit',   '', 'sys:log:update', 303,  3, 'B', 1, 1, 0),
(3033, 'SystemLogDelete', '删除日志',   '/system/log/delete', '', 'sys:log:delete', 303,  4, 'B', 1, 1, 0);

-- =====================================================
-- swagger 文档菜单特殊配置（link 字段不在上方批量插入的列定义中，单独 UPDATE）
-- =====================================================
UPDATE `na_sys_menu`
   SET `link`   = '/sys/openapi',
       `iframe` = 1
 WHERE `id` = 400;

-- 重置自增ID
ALTER TABLE `na_sys_menu` AUTO_INCREMENT = 8000;

-- =====================================================
-- 表结构说明
-- =====================================================

-- 菜单类型说明：
-- M（Menu）: 菜单页面 - 对应具体功能页面
-- D（Directory）: 目录 - 纯容器，不挂组件
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
