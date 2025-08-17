-- TheAdmin 菜单数据更新辅助脚本
-- 用于维护和更新菜单数据

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
UPDATE th_sys_menu SET cacheable = TRUE 
WHERE parent_id = (SELECT id FROM (SELECT id FROM th_sys_menu WHERE name = 'System' AND parent_id = 0) as temp)
  AND deleted = FALSE;

-- 5. 添加新菜单的模板
/*
INSERT INTO th_sys_menu (
    parent_id, name, path, component, title, icon, type, 
    roles, hidden, cacheable, affix, auth_list, status, sort, created_at, updated_at
) VALUES (
    0, -- parent_id: 0为顶级菜单，其他为父菜单ID
    'NewModule', -- name: 路由名称
    '/new-module', -- path: 路由路径
    '/index/index', -- component: 组件路径，顶级菜单通常为 /index/index
    '新模块', -- title: 菜单标题
    '&#xe123;', -- icon: 菜单图标
    'D', -- type: D=目录，M=菜单，B=按钮
    JSON_ARRAY('R_SUPER', 'R_ADMIN'), -- roles: 角色权限数组
    FALSE, -- hidden: 是否隐藏
    TRUE, -- cacheable: 是否缓存
    FALSE, -- affix: 是否固定标签
    NULL, -- auth_list: 权限按钮列表
    1, -- status: 状态
    500, -- sort: 排序
    NOW(), -- created_at
    NOW() -- updated_at
);
*/

-- 6. 权限按钮配置示例
/*
UPDATE th_sys_menu SET auth_list = JSON_ARRAY(
    JSON_OBJECT('title', '查看', 'authMark', 'view'),
    JSON_OBJECT('title', '新增', 'authMark', 'add'),
    JSON_OBJECT('title', '编辑', 'authMark', 'edit'),
    JSON_OBJECT('title', '删除', 'authMark', 'delete'),
    JSON_OBJECT('title', '导出', 'authMark', 'export')
) WHERE name = 'MenuName' AND deleted = FALSE;
*/

-- ========================================
-- 角色菜单权限管理
-- ========================================

-- 7. 为新角色分配菜单权限
/*
-- 创建新角色
INSERT INTO th_sys_role (code, name, description, status, sort, created_at, updated_at) 
VALUES ('R_EDITOR', '编辑员', '内容编辑员角色', 1, 4, NOW(), NOW());

-- 为新角色分配特定菜单权限
INSERT INTO th_sys_role_menu (role_id, menu_id) 
SELECT 
    (SELECT id FROM th_sys_role WHERE code = 'R_EDITOR'),
    m.id 
FROM th_sys_menu m 
WHERE m.name IN ('Dashboard', 'Console', 'Result', 'ResultSuccess', 'ResultFail')
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

-- 9. 同步前端路由配置变更
-- 当前端路由配置发生变化时，使用此脚本同步数据库

-- 检查前端配置与数据库的差异
SELECT 
    '配置差异检查' as check_type,
    m.name,
    m.path,
    m.component,
    m.title,
    CASE 
        WHEN m.component = '/index/index' AND m.type != 'D' THEN '组件路径与类型不匹配'
        WHEN m.component != '/index/index' AND m.type = 'D' THEN '目录类型组件路径错误'
        ELSE '正常'
    END as status
FROM th_sys_menu m 
WHERE m.deleted = FALSE
ORDER BY m.sort, m.id;

-- 10. 重建菜单权限关联
-- 清理并重建角色菜单关联关系
/*
-- 清理现有关联
DELETE FROM th_sys_role_menu;

-- 重建超级管理员权限（所有菜单）
INSERT INTO th_sys_role_menu (role_id, menu_id) 
SELECT 1, id FROM th_sys_menu WHERE deleted = FALSE;

-- 重建管理员权限（除敏感菜单外）
INSERT INTO th_sys_role_menu (role_id, menu_id) 
SELECT 2, id FROM th_sys_menu 
WHERE deleted = FALSE 
  AND name NOT IN ('Menus'); -- 排除菜单管理

-- 重建普通用户权限（基础菜单）
INSERT INTO th_sys_role_menu (role_id, menu_id) 
SELECT 3, id FROM th_sys_menu 
WHERE deleted = FALSE 
  AND name IN ('Dashboard', 'Console', 'Result', 'ResultSuccess', 'ResultFail');
*/