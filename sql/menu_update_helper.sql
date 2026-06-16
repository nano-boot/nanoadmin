-- NanoAdmin 菜单数据更新辅助脚本（统一口径版）
-- 用于维护和更新菜单数据
-- 当前正式菜单动作模型：B 类型按钮节点 + permission
-- auth_list 仅作为历史兼容/静态说明概念，不作为新功能主模型

-- ========================================
-- 菜单数据维护工具
-- ========================================

-- 1. 清理无效的菜单数据
-- 删除父菜单不存在的子菜单
DELETE FROM th_sys_menu 
WHERE parent_id > 0 
  AND parent_id NOT IN (SELECT id FROM (SELECT id FROM th_sys_menu WHERE parent_id = 0 AND deleted = FALSE) as temp);

-- 2. 修复菜单排序
-- 重新设置菜单排序，确保层级清晰
UPDATE th_sys_menu SET sort = 100 WHERE parent_id = 0 AND name = 'Dashboard';
UPDATE th_sys_menu SET sort = 200 WHERE parent_id = 0 AND name = 'System';
UPDATE th_sys_menu SET sort = 300 WHERE parent_id = 0 AND name = 'Result';
UPDATE th_sys_menu SET sort = 400 WHERE parent_id = 0 AND name = 'Exception';

-- 3. 批量更新菜单状态
-- 启用所有菜单
-- UPDATE th_sys_menu SET status = 1 WHERE deleted = FALSE;

-- 禁用特定菜单（示例）
-- UPDATE th_sys_menu SET status = 0 WHERE name IN ('UserCenter') AND deleted = FALSE;

-- 4. 批量设置菜单缓存属性
-- 设置系统管理模块的菜单为可缓存
UPDATE th_sys_menu SET cache = TRUE 
WHERE parent_id = (SELECT id FROM (SELECT id FROM th_sys_menu WHERE name = 'System' AND parent_id = 0) as temp)
  AND deleted = FALSE;

-- 5. 添加新菜单的模板（目录/页面节点）
/*
INSERT INTO th_sys_menu (
    parent_id, name, path, component, title, icon, redirect,
    type, permission, hide, hide_tab, cache, fixed_tab, active_path,
    status, sort, created_at, updated_at
) VALUES (
    0, -- parent_id: 0为顶级菜单，其他为父菜单ID
    'NewModule', -- name: 路由名称
    '/new-module', -- path: 路由路径
    '/index/index', -- component: 组件路径，顶级菜单通常为 /index/index
    '新模块', -- title: 菜单标题
    '&#xe123;', -- icon: 菜单图标
    '', -- redirect: 重定向
    'D', -- type: D=目录，M=菜单，B=按钮
    '', -- permission: 目录/页面节点通常留空；按钮节点才挂正式权限码
    0, -- hide
    0, -- hide_tab
    1, -- cache
    0, -- fixed_tab
    '', -- active_path
    1, -- status
    500, -- sort
    NOW(), -- created_at
    NOW() -- updated_at
);
*/

-- 6. 添加页面下按钮节点示例（正式动作权限建模方式）
/*
INSERT INTO th_sys_menu (
    parent_id, name, path, component, title, permission,
    type, status, hide, sort, active_path, created_at, updated_at
) VALUES (
    200, -- parent_id: 所属页面菜单ID
    'AuthUserCreate',
    '/permission/user/create',
    '',
    '创建用户',
    'sys:admin:create', -- 必须与 th_sys_permission.code 保持同一值域
    'B',
    1,
    1,
    100,
    '',
    NOW(),
    NOW()
);
*/

-- 6.1 页面按钮节点批量模板（推荐为每个页面显式补一个 list/page 入口）
/*
INSERT INTO th_sys_menu (
    parent_id, name, path, component, title, permission,
    type, status, hide, sort, active_path, created_at, updated_at
) VALUES
    (200, 'AuthUserList', '/permission/user/list', '', '用户列表', 'sys:admin:page', 'B', 1, 1, 1, '', NOW(), NOW()),
    (200, 'AuthUserCreate', '/permission/user/create', '', '创建用户', 'sys:admin:create', 'B', 1, 1, 2, '', NOW(), NOW()),
    (200, 'AuthUserEdit', '/permission/user/edit', '', '编辑用户', 'sys:admin:update', 'B', 1, 1, 3, '', NOW(), NOW()),
    (200, 'AuthUserDelete', '/permission/user/delete', '', '删除用户', 'sys:admin:delete', 'B', 1, 1, 4, '', NOW(), NOW()),
    (201, 'AuthRoleList', '/permission/role/list', '', '角色列表', 'sys:role:page', 'B', 1, 1, 1, '', NOW(), NOW()),
    (201, 'AuthRoleCreate', '/permission/role/create', '', '创建角色', 'sys:role:create', 'B', 1, 1, 2, '', NOW(), NOW()),
    (201, 'AuthRoleEdit', '/permission/role/edit', '', '编辑角色', 'sys:role:update', 'B', 1, 1, 3, '', NOW(), NOW()),
    (201, 'AuthRoleDelete', '/permission/role/delete', '', '删除角色', 'sys:role:delete', 'B', 1, 1, 4, '', NOW(), NOW()),
    (202, 'AuthMenuList', '/permission/menu/list', '', '菜单列表', 'sys:menu:page', 'B', 1, 1, 1, '', NOW(), NOW()),
    (202, 'AuthMenuCreate', '/permission/menu/create', '', '创建菜单', 'sys:menu:create', 'B', 1, 1, 2, '', NOW(), NOW()),
    (202, 'AuthMenuEdit', '/permission/menu/edit', '', '编辑菜单', 'sys:menu:update', 'B', 1, 1, 3, '', NOW(), NOW()),
    (202, 'AuthMenuDelete', '/permission/menu/delete', '', '删除菜单', 'sys:menu:delete', 'B', 1, 1, 4, '', NOW(), NOW()),
    (202, 'AuthMenuSort', '/permission/menu/sort', '', '菜单排序', 'sys:menu:sort', 'B', 1, 1, 5, '', NOW(), NOW());
*/

-- 6.2 文件管理按钮节点示例（支持列表/创建/编辑/删除权限拆分）
/*
INSERT INTO th_sys_menu (
    parent_id, name, path, component, title, permission,
    type, status, hide, sort, active_path, created_at, updated_at
) VALUES
    (300, 'SystemFileList', '/system/file/list', '', '文件列表', 'sys:file:list', 'B', 1, 1, 100, '', NOW(), NOW()),
    (300, 'SystemFileCreate', '/system/file/create', '', '创建文件', 'sys:file:create', 'B', 1, 1, 101, '', NOW(), NOW()),
    (300, 'SystemFileEdit', '/system/file/edit', '', '编辑文件', 'sys:file:update', 'B', 1, 1, 102, '', NOW(), NOW()),
    (300, 'SystemFileDelete', '/system/file/delete', '', '删除文件', 'sys:file:delete', 'B', 1, 1, 103, '', NOW(), NOW());
*/


-- 7. 为新角色分配菜单权限
/*
-- 创建新角色
INSERT INTO th_sys_role (code, name, description, status, sort, created_at, updated_at) 
VALUES ('R_EDITOR', '编辑员', '内容编辑员角色', 1, 4, NOW(), NOW());

-- 为新角色分配特定菜单/按钮节点权限入口
INSERT INTO th_sys_role_menu (role_id, menu_id) 
SELECT 
    (SELECT id FROM th_sys_role WHERE code = 'R_EDITOR'),
    m.id 
FROM th_sys_menu m 
WHERE m.name IN ('Dashboard', 'Console', 'AuthUser', 'AuthUserCreate')
  AND m.deleted = FALSE;
*/

-- 8. 移除角色的特定菜单权限
/*
DELETE FROM th_sys_role_menu 
WHERE role_id = (SELECT id FROM th_sys_role WHERE code = 'R_USER')
  AND menu_id IN (SELECT id FROM th_sys_menu WHERE name = 'System');
*/

-- ========================================
-- 数据同步和验证
-- ========================================

-- 9. 检查数据库菜单结构
SELECT 
    '配置差异检查' as check_type,
    m.name,
    m.path,
    m.component,
    m.title,
    m.type,
    m.permission,
    CASE 
        WHEN m.type = 'B' AND m.permission = '' THEN '按钮节点缺少权限码'
        WHEN m.component = '/index/index' AND m.type NOT IN ('D', 'M') THEN '组件路径与类型不匹配'
        WHEN m.component != '/index/index' AND m.type = 'D' THEN '目录类型组件路径错误'
        ELSE '正常'
    END as status
FROM th_sys_menu m 
WHERE m.deleted = FALSE
ORDER BY m.sort, m.id;

-- 10. 文件管理权限升级 SQL（适用于已有环境）
/*
START TRANSACTION;

-- 10.1 补齐文件权限字典
INSERT INTO `th_sys_permission` (`code`, `name`, `resource`, `action`, `description`, `status`, `sort`, `deleted`)
VALUES
    ('sys:file:list', '文件列表', 'file', 'list', '查看文件列表', 1, 500, 0),
    ('sys:file:create', '创建文件', 'file', 'create', '上传或创建文件', 1, 501, 0),
    ('sys:file:update', '编辑文件', 'file', 'update', '编辑文件信息', 1, 502, 0),
    ('sys:file:delete', '删除文件', 'file', 'delete', '删除文件', 1, 503, 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `resource` = VALUES(`resource`),
    `action` = VALUES(`action`),
    `description` = VALUES(`description`),
    `status` = VALUES(`status`),
    `sort` = VALUES(`sort`),
    `deleted` = VALUES(`deleted`);

-- 10.2 补齐文件管理按钮节点
INSERT INTO `th_sys_menu` (
    `id`, `parent_id`, `name`, `path`, `component`, `permission`, `title`, `icon`, `sort`, `type`, `status`, `hide`, `redirect`
) VALUES
    (3001, 300, 'SystemFileList', '/system/file/list', '', 'sys:file:list', '文件列表', '', 1, 'B', 1, 1, ''),
    (3002, 300, 'SystemFileCreate', '/system/file/create', '', 'sys:file:create', '创建文件', '', 2, 'B', 1, 1, ''),
    (3003, 300, 'SystemFileEdit', '/system/file/edit', '', 'sys:file:update', '编辑文件', '', 3, 'B', 1, 1, ''),
    (3004, 300, 'SystemFileDelete', '/system/file/delete', '', 'sys:file:delete', '删除文件', '', 4, 'B', 1, 1, '')
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `component` = VALUES(`component`),
    `permission` = VALUES(`permission`),
    `title` = VALUES(`title`),
    `icon` = VALUES(`icon`),
    `sort` = VALUES(`sort`),
    `type` = VALUES(`type`),
    `status` = VALUES(`status`),
    `hide` = VALUES(`hide`),
    `redirect` = VALUES(`redirect`),
    `deleted` = 0;

-- 10.3 为普通管理员补齐文件权限（如需更细控制，可按角色自行裁剪）
INSERT INTO `th_sys_role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id
FROM `th_sys_permission` p
WHERE p.code IN ('sys:file:list', 'sys:file:create', 'sys:file:update', 'sys:file:delete')
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- 10.4 为普通管理员补齐文件按钮节点授权入口
INSERT INTO `th_sys_role_menu` (`role_id`, `menu_id`)
SELECT 2, m.id
FROM `th_sys_menu` m
WHERE m.id IN (3001, 3002, 3003, 3004)
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`);

COMMIT;
*/

-- 11. 字典/配置/日志权限升级 SQL（适用于已有环境）
/*
START TRANSACTION;

-- 11.1 补齐字典、配置、日志权限字典
INSERT INTO `th_sys_permission` (`code`, `name`, `resource`, `action`, `description`, `status`, `sort`, `deleted`)
VALUES
    ('sys:dict:type:page', '字典列表', 'dict-type', 'page', '查看字典列表', 1, 600, 0),
    ('sys:dict:type:create', '创建字典', 'dict-type', 'create', '创建新字典', 1, 601, 0),
    ('sys:dict:type:update', '编辑字典', 'dict-type', 'update', '编辑字典信息', 1, 602, 0),
    ('sys:dict:type:delete', '删除字典', 'dict-type', 'delete', '删除字典', 1, 603, 0),
    ('sys:config:page', '配置列表', 'config', 'page', '查看配置列表', 1, 700, 0),
    ('sys:config:create', '创建配置', 'config', 'create', '创建新配置', 1, 701, 0),
    ('sys:config:update', '编辑配置', 'config', 'update', '编辑配置信息', 1, 702, 0),
    ('sys:config:delete', '删除配置', 'config', 'delete', '删除配置', 1, 703, 0),
    ('sys:log:page', '日志列表', 'log', 'page', '查看日志列表', 1, 800, 0),
    ('sys:log:create', '创建日志', 'log', 'create', '创建日志记录', 1, 801, 0),
    ('sys:log:update', '编辑日志', 'log', 'update', '编辑日志信息', 1, 802, 0),
    ('sys:log:delete', '删除日志', 'log', 'delete', '删除日志', 1, 803, 0)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `resource` = VALUES(`resource`),
    `action` = VALUES(`action`),
    `description` = VALUES(`description`),
    `status` = VALUES(`status`),
    `sort` = VALUES(`sort`),
    `deleted` = VALUES(`deleted`);

-- 11.2 补齐字典、配置、日志按钮节点
INSERT INTO `th_sys_menu` (
    `id`, `parent_id`, `name`, `path`, `component`, `permission`, `title`, `icon`, `sort`, `type`, `status`, `hide`, `redirect`
) VALUES
    (3010, 301, 'SystemDictTypeList', '/system/dict-type/list', '', 'sys:dict:type:page', '字典列表', '', 1, 'B', 1, 1, ''),
    (3011, 301, 'SystemDictTypeCreate', '/system/dict-type/create', '', 'sys:dict:type:create', '创建字典', '', 2, 'B', 1, 1, ''),
    (3012, 301, 'SystemDictEdit', '/system/dict-type/edit', '', 'sys:dict:type:update', '编辑字典', '', 3, 'B', 1, 1, ''),
    (3013, 301, 'SystemDictDelete', '/system/dict-type/delete', '', 'sys:dict:type:delete', '删除字典', '', 4, 'B', 1, 1, ''),
    (3020, 302, 'SystemConfigList', '/system/config/list', '', 'sys:config:page', '配置列表', '', 1, 'B', 1, 1, ''),
    (3021, 302, 'SystemConfigCreate', '/system/config/create', '', 'sys:config:create', '创建配置', '', 2, 'B', 1, 1, ''),
    (3022, 302, 'SystemConfigEdit', '/system/config/edit', '', 'sys:config:update', '编辑配置', '', 3, 'B', 1, 1, ''),
    (3023, 302, 'SystemConfigDelete', '/system/config/delete', '', 'sys:config:delete', '删除配置', '', 4, 'B', 1, 1, ''),
    (3030, 303, 'SystemLogList', '/system/log/list', '', 'sys:log:page', '日志列表', '', 1, 'B', 1, 1, ''),
    (3031, 303, 'SystemLogCreate', '/system/log/create', '', 'sys:log:create', '创建日志', '', 2, 'B', 1, 1, ''),
    (3032, 303, 'SystemLogEdit', '/system/log/edit', '', 'sys:log:update', '编辑日志', '', 3, 'B', 1, 1, ''),
    (3033, 303, 'SystemLogDelete', '/system/log/delete', '', 'sys:log:delete', '删除日志', '', 4, 'B', 1, 1, '')
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `component` = VALUES(`component`),
    `permission` = VALUES(`permission`),
    `title` = VALUES(`title`),
    `icon` = VALUES(`icon`),
    `sort` = VALUES(`sort`),
    `type` = VALUES(`type`),
    `status` = VALUES(`status`),
    `hide` = VALUES(`hide`),
    `redirect` = VALUES(`redirect`),
    `deleted` = 0;

-- 11.3 为普通管理员补齐字典/配置/日志权限
INSERT INTO `th_sys_role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id
FROM `th_sys_permission` p
WHERE p.code IN (
    'sys:dict:type:page', 'sys:dict:type:create', 'sys:dict:type:update', 'sys:dict:type:delete',
    'sys:config:page', 'sys:config:create', 'sys:config:update', 'sys:config:delete',
    'sys:log:page', 'sys:log:create', 'sys:log:update', 'sys:log:delete'
)
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- 11.4 为普通管理员补齐字典/配置/日志按钮节点授权入口
INSERT INTO `th_sys_role_menu` (`role_id`, `menu_id`)
SELECT 2, m.id
FROM `th_sys_menu` m
WHERE m.id IN (3010, 3011, 3012, 3013, 3020, 3021, 3022, 3023, 3030, 3031, 3032, 3033)
ON DUPLICATE KEY UPDATE `menu_id` = VALUES(`menu_id`);

COMMIT;
*/
