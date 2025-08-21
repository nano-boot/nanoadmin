-- TheAdmin 菜单初始化数据脚本
-- 基于前端路由配置生成对应的菜单数据
-- 确保与前端配置完全匹配
-- 
-- 使用说明：
-- 1. 本脚本基于 theadmin-vue/src/router/routes/asyncRoutes.ts 配置生成
-- 2. 菜单类型说明：D=目录，M=菜单，B=按钮，L=外链，I=内嵌
-- 3. 角色权限：R_SUPER=超级管理员，R_ADMIN=管理员，R_USER=普通用户
-- 4. 组件路径对应 theladmin-vue/src/router/routesAlias.ts 中的别名
-- 
-- 执行前请确保：
-- - 数据库表结构已更新到最新版本
-- - 备份现有菜单数据（如有需要）

-- 清空现有菜单数据（可选，根据需要启用）
-- DELETE FROM th_sys_menu WHERE deleted = FALSE;

-- 插入菜单数据
-- 注意：ID使用自增，这里手动指定是为了方便维护父子关系

-- 1. 仪表板模块
INSERT INTO th_sys_menu (
    id, parent_id, name, path, component, title, icon, type, 
     hidden, keep_alive, fixed_tab, status, sort, created_at, updated_at
) VALUES 
-- 仪表板父级菜单
(1, 0, 'Dashboard', '/dashboard', '/index/index', '仪表板', '&#xe721;', 'D', 
FALSE, TRUE, FALSE, 1, 100, NOW(), NOW()),

-- 仪表板子菜单
(2, 1, 'Console', 'console', '/dashboard/console', '工作台', '', 'M', 
  FALSE, FALSE, TRUE, 1, 100, NOW(), NOW());

-- 2. 系统管理模块
INSERT INTO th_sys_menu (
    id, parent_id, name, path, component, title, icon, type, 
     hidden, keep_alive, fixed_tab, status, sort, created_at, updated_at
) VALUES 
-- 系统管理父级菜单
(10, 0, 'System', '/system', '/index/index', '系统管理', '&#xe7b9;', 'D', 
  FALSE, TRUE, FALSE, 1, 200, NOW(), NOW()),

-- 用户管理
(11, 10, 'User', 'user', '/system/user', '用户管理', '', 'M', 
FALSE, TRUE, FALSE,  1, 100, NOW(), NOW()),

-- 角色管理
(12, 10, 'Role', 'role', '/system/role', '角色管理', '', 'M', 
FALSE, TRUE, FALSE, 1, 200, NOW(), NOW()),

-- 用户中心（隐藏菜单）
(13, 10, 'UserCenter', 'user-center', '/system/user-center', '用户中心', '', 'M', 
 TRUE, TRUE, FALSE, 1, 300, NOW(), NOW()),

-- 菜单管理（包含权限按钮）
(14, 10, 'Menus', 'menu', '/system/menu', '菜单管理', '', 'M', 
FALSE, TRUE, FALSE,  1, 400, NOW(), NOW());

-- 3. 结果页面模块
INSERT INTO th_sys_menu (
    id, parent_id, name, path, component, title, icon, type, 
    hidden, keep_alive, fixed_tab, status, sort, created_at, updated_at
) VALUES 
-- 结果页面父级菜单
(20, 0, 'Result', '/result', '/index/index', '结果页面', '&#xe715;', 'D', 
 FALSE, TRUE, FALSE, 1, 300, NOW(), NOW()),

-- 成功页面
(21, 20, 'ResultSuccess', 'success', '/result/success', '成功页面', '', 'M', 
 FALSE, TRUE, FALSE, 1, 100, NOW(), NOW()),

-- 失败页面
(22, 20, 'ResultFail', 'fail', '/result/fail', '失败页面', '', 'M', 
 FALSE, TRUE, FALSE, 1, 200, NOW(), NOW());

-- 4. 异常页面模块
INSERT INTO th_sys_menu (
    id, parent_id, name, path, component, title, icon, type, 
    hidden, keep_alive, fixed_tab, status, sort, created_at, updated_at
) VALUES 
-- 异常页面父级菜单
(30, 0, 'Exception', '/exception', '/index/index', '异常页面', '&#xe820;', 'D', 
 FALSE, TRUE, FALSE, 1, 400, NOW(), NOW()),

-- 403页面
(31, 30, '403', '403', '/exception/403', '403页面', '', 'M', 
 FALSE, TRUE, FALSE, 1, 100, NOW(), NOW()),

-- 404页面
(32, 30, '404', '404', '/exception/404', '404页面', '', 'M', 
 FALSE, TRUE, FALSE, 1, 200, NOW(), NOW()),

-- 500页面
(33, 30, '500', '500', '/exception/500', '500页面', '', 'M', 
 FALSE, TRUE, FALSE, 1, 300, NOW(), NOW());

-- 重置自增ID（可选，确保后续插入的ID从合适的值开始）
ALTER TABLE th_sys_menu AUTO_INCREMENT = 100;

-- 插入默认角色数据（如果不存在）
INSERT IGNORE INTO th_sys_role (id, code, name, description, status, sort, created_at, updated_at) VALUES 
(1, 'R_SUPER', '超级管理员', '系统超级管理员，拥有所有权限', 1, 1, NOW(), NOW()),
(2, 'R_ADMIN', '管理员', '系统管理员，拥有大部分权限', 1, 2, NOW(), NOW()),
(3, 'R_USER', '普通用户', '普通用户，拥有基础权限', 1, 3, NOW(), NOW());

-- 插入默认管理员账户（如果不存在）
-- 密码为 admin123，使用 password_hash() 函数加密
INSERT IGNORE INTO th_sys_admin (id, username, password, nickname, status, created_at, updated_at) VALUES 
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 1, NOW(), NOW());

-- 为默认管理员分配超级管理员角色
INSERT IGNORE INTO th_sys_admin_role (admin_id, role_id) VALUES (1, 1);

-- 为角色分配菜单权限
-- 超级管理员拥有所有菜单权限
INSERT IGNORE INTO th_sys_role_menu (role_id, menu_id) 
SELECT 1, id FROM th_sys_menu WHERE deleted = FALSE;

-- 管理员拥有除菜单管理外的所有权限
INSERT IGNORE INTO th_sys_role_menu (role_id, menu_id) 
SELECT 2, id FROM th_sys_menu WHERE deleted = FALSE AND id != 14;

-- 普通用户只能访问仪表板和结果页面
INSERT IGNORE INTO th_sys_role_menu (role_id, menu_id) VALUES 
(3, 1), (3, 2), -- 仪表板
(3, 20), (3, 21), (3, 22); -- 结果页面

-- ========================================
-- 数据验证和完整性检查
-- ========================================

-- 1. 验证菜单数据插入结果
SELECT 
    '菜单数据验证' as check_type,
    COUNT(*) as total_menus,
    COUNT(CASE WHEN parent_id = 0 THEN 1 END) as parent_menus,
    COUNT(CASE WHEN parent_id > 0 THEN 1 END) as child_menus
FROM th_sys_menu 
WHERE deleted = FALSE;

-- 2. 显示完整菜单列表
SELECT 
    m.id,
    m.parent_id,
    m.name,
    m.path,
    m.title,
    m.type,
    m.icon,
    m.roles,
    m.hidden,
    m.cacheable,
    m.sort
FROM th_sys_menu m 
WHERE m.deleted = FALSE 
ORDER BY m.sort, m.id;

-- 3. 显示菜单层级结构
SELECT 
    CASE 
        WHEN m.parent_id = 0 THEN CONCAT('├─ ', m.title, ' (', m.name, ')')
        ELSE CONCAT('│  ├─ ', m.title, ' (', m.name, ')')
    END AS menu_tree,
    m.path,
    m.component,
    m.type,
    CASE 
        WHEN m.roles IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(m.roles, '$'))
        ELSE '无权限限制'
    END AS roles
FROM th_sys_menu m 
WHERE m.deleted = FALSE 
ORDER BY 
    CASE WHEN m.parent_id = 0 THEN m.id ELSE m.parent_id END,
    m.parent_id,
    m.sort;

-- 4. 验证角色菜单关联
SELECT 
    r.name as role_name,
    COUNT(rm.menu_id) as menu_count
FROM th_sys_role r
LEFT JOIN th_sys_role_menu rm ON r.id = rm.role_id
GROUP BY r.id, r.name
ORDER BY r.sort;

-- 5. 检查数据完整性
SELECT 
    '数据完整性检查' as check_type,
    CASE 
        WHEN EXISTS(SELECT 1 FROM th_sys_menu WHERE parent_id > 0 AND parent_id NOT IN (SELECT id FROM th_sys_menu WHERE parent_id = 0)) 
        THEN '发现孤立子菜单' 
        ELSE '菜单层级关系正常' 
    END as parent_child_check,
    CASE 
        WHEN EXISTS(SELECT 1 FROM th_sys_menu WHERE component = '' AND type = 'M') 
        THEN '发现组件路径为空的菜单' 
        ELSE '菜单组件路径正常' 
    END as component_check;

-- 6. 显示权限按钮配置
SELECT 
    m.title as menu_title,
    m.name as menu_name,
    JSON_PRETTY(m.auth_list) as auth_buttons
FROM th_sys_menu m 
WHERE m.auth_list IS NOT NULL 
  AND JSON_LENGTH(m.auth_list) > 0
  AND m.deleted = FALSE;