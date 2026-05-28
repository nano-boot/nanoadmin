-- 日志管理菜单初始化
-- 依赖: menu_init.sql 中已存在 id=303 的 "日志管理" 父菜单
-- 使用 REPLACE INTO 避免重复插入

-- 登录日志菜单
REPLACE INTO `th_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `icon`, `redirect`,
    `parent_id`, `sort`, `type`, `status`,
    `hide`, `hide_tab`, `cache`, `fixed_tab`, `active_path`
) VALUES (
    304, 'SystemLoginLog', '登录日志', '/system/login-log', '/system/login-log', 'ri:user-follow-line', '',
    303, 1, 'M', 1, 0, 0, 1, 0, ''
);

-- 操作日志菜单
REPLACE INTO `th_sys_menu` (
    `id`, `name`, `title`, `path`, `component`, `icon`, `redirect`,
    `parent_id`, `sort`, `type`, `status`,
    `hide`, `hide_tab`, `cache`, `fixed_tab`, `active_path`
) VALUES (
    305, 'SystemOperationLog', '操作日志', '/system/operation-log', '/system/operation-log', 'ri:history-line', '',
    303, 2, 'M', 1, 0, 0, 1, 0, ''
);
