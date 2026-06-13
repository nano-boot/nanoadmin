-- nanoadmin数据库建表语句（无外键约束版本）
-- 数据库: nanoadmin
-- 字符集: utf8mb4
-- 排序规则: utf8mb4_unicode_ci

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS nanoadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE nanoadmin;

-- 1. 用户表
CREATE TABLE IF NOT EXISTS `th_sys_user` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL default '' UNIQUE COMMENT '昵称',
    `password` VARCHAR(255) NOT NULL default '' COMMENT '密码（加密后）',
    `avatar` VARCHAR(255) DEFAULT '' COMMENT '头像',
    `gender` tinyint(1) DEFAULT '0' COMMENT '性别（0未知 1男 2女）',
    `phone` VARCHAR(20) default '' COMMENT '手机号',
    `unionid` VARCHAR(32) default '' COMMENT 'unionid',
    `last_login_ip` VARCHAR(50) DEFAULT '' COMMENT '最后登录IP',
    `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    account_non_expired BOOLEAN NOT NULL DEFAULT TRUE COMMENT '账户是否未过期',
    account_non_locked BOOLEAN NOT NULL DEFAULT TRUE COMMENT '账户是否未锁定',
    credentials_non_expired BOOLEAN NOT NULL DEFAULT TRUE COMMENT '凭证是否未过期',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    
    INDEX idx_nickname (nickname),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 7. 管理员表
-- ----------------------------
DROP TABLE IF EXISTS `th_sys_admin`;
CREATE TABLE `th_sys_admin` (
    `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '管理员ID',
    `username` varchar(50) NOT NULL COMMENT '用户名',
    `password` varchar(255) NOT NULL COMMENT '密码',
    `nickname` varchar(50) DEFAULT '' COMMENT '昵称',
    `gender` tinyint(1) DEFAULT '0' COMMENT '性别（0未知 1男 2女）',
    `avatar` varchar(255) DEFAULT '' COMMENT '头像',
    `phone` varchar(20) DEFAULT '' COMMENT '手机号',
    `email` varchar(100) DEFAULT '' COMMENT '邮箱',
    `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
    `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    INDEX idx_nickname (nickname),
    UNIQUE KEY `idx_username` (`username`),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';


-- 2. 角色表
CREATE TABLE IF NOT EXISTS th_sys_role (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '角色ID',
    code VARCHAR(50) NOT NULL UNIQUE COMMENT '角色代码',
    name VARCHAR(100) NOT NULL COMMENT '角色名称',
    description VARCHAR(500) COMMENT '角色描述',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    `sort` int(11) DEFAULT 100 COMMENT '排序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    
    INDEX idx_role_code (code),
    INDEX idx_status (status),
    INDEX idx_sort (sort),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

-- 3. 权限表
CREATE TABLE IF NOT EXISTS th_sys_permission (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '权限ID',
    code VARCHAR(100) NOT NULL UNIQUE COMMENT '权限代码',
    name VARCHAR(100) NOT NULL COMMENT '权限名称',
    resource VARCHAR(50) NOT NULL COMMENT '资源类型',
    action VARCHAR(50) NOT NULL COMMENT '操作类型',
    description VARCHAR(500) COMMENT '权限描述',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    `sort` int(11) DEFAULT 100 COMMENT '排序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    
    INDEX idx_permission_code (code),
    INDEX idx_resource_action (resource, action),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限表';

-- 4. 菜单表
CREATE TABLE IF NOT EXISTS th_sys_menu (
    -- 基础字段
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '菜单ID',
    parent_id BIGINT DEFAULT 0 COMMENT '父菜单ID，0为顶级菜单',
    
    -- 菜单显示信息（核心字段）
    name VARCHAR(100) NOT NULL COMMENT '菜单名称（对应meta.title）',
    icon VARCHAR(100) DEFAULT '' COMMENT '菜单图标（对应meta.icon）',
    
    -- 路由信息
    path VARCHAR(200) DEFAULT '' COMMENT '路由路径',
    component VARCHAR(200) DEFAULT '' COMMENT '组件路径',
    redirect VARCHAR(200) DEFAULT '' COMMENT '重定向路径',
    
    -- 菜单类型和权限
    type CHAR(1) DEFAULT 'D' COMMENT '菜单类型（D目录 M菜单 B按钮 L外链 I内嵌）',
    permission VARCHAR(100) DEFAULT '' COMMENT '权限标识（正式动作权限载体；当 type = ''B'' 时应与 th_sys_permission.code 保持同一值域）',
    -- roles / auth_list 为历史设计注释口径，当前正式动作权限模型以 B 类型按钮节点 + permission 为准

    -- === 显示控制 ===
    hide tinyint(1) DEFAULT 0 COMMENT '是否在菜单中隐藏（对应meta.isHide）',
    hide_tab tinyint(1) DEFAULT 0 COMMENT '是否在标签页中隐藏：1-隐藏，0-显示（对应meta.isHideTab）',
    full_page tinyint(1) DEFAULT 0 COMMENT '是否全屏显示（对应meta.isFullPage）',

    -- === 缓存和固定 ===
    cache tinyint(1) DEFAULT 1 COMMENT '是否缓存（对应meta.keepAlive）',
    fixed_tab tinyint(1) DEFAULT 0 COMMENT '是否固定标签（对应meta.fixedTab）',

    
    -- 外链配置
    link VARCHAR(500) DEFAULT '' COMMENT '外链地址（对应meta.link）',
    iframe tinyint(1) DEFAULT 0 COMMENT '是否内嵌（对应meta.isIframe）',
    
    -- 徽章配置
    show_badge tinyint(1) DEFAULT 0 COMMENT '是否显示徽章（对应meta.showBadge）',
    badge_text VARCHAR(20) DEFAULT '' COMMENT '徽章文本（对应meta.showTextBadge）',

    -- 其他属性
    active_path VARCHAR(200) DEFAULT '' COMMENT '激活菜单路径（对应meta.activePath）',
    
    -- 状态和排序
    status TINYINT(1) DEFAULT 1 COMMENT '状态（0禁用 1正常）',
    sort INT(11) DEFAULT 100 COMMENT '排序',
    
    -- 审计字段
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    
    -- 索引
    INDEX idx_parent_id (parent_id),
    INDEX idx_path (path),
    INDEX idx_name (name),
    INDEX idx_permission (permission),
    INDEX idx_type (type),
    INDEX idx_sort (sort),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单表';


-- 5. 用户角色关联表
CREATE TABLE IF NOT EXISTS th_sys_admin_role (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    admin_id BIGINT NOT NULL COMMENT '管理员ID',
    role_id BIGINT NOT NULL COMMENT '角色ID',
    UNIQUE KEY `uk_admin_role` (admin_id, role_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色关联表';

-- 6. 角色权限关联表
CREATE TABLE IF NOT EXISTS th_sys_role_permission (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    role_id BIGINT NOT NULL COMMENT '角色ID',
    permission_id BIGINT NOT NULL COMMENT '权限ID',
    UNIQUE KEY `uk_role_permission` (role_id, permission_id),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- 7. 角色菜单关联表
CREATE TABLE IF NOT EXISTS th_sys_role_menu (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    role_id BIGINT NOT NULL COMMENT '角色ID',
    menu_id BIGINT NOT NULL COMMENT '菜单ID',
    UNIQUE KEY `uk_role_menu` (role_id, menu_id),
    INDEX idx_role_id (role_id),
    INDEX idx_menu_id (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色菜单关联表';

-- 8. 文件表
CREATE TABLE IF NOT EXISTS th_sys_file (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '文件ID',
    original_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_name VARCHAR(255) NOT NULL COMMENT '存储文件名',
    file_path VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    file_size BIGINT NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
    file_ext VARCHAR(20) DEFAULT '' COMMENT '文件扩展名',
    mime_type VARCHAR(100) DEFAULT '' COMMENT 'MIME类型',
    file_hash VARCHAR(128) DEFAULT '' COMMENT '文件哈希值（MD5/SHA256）',
    file_type ENUM('image', 'video', 'document', 'audio', 'archive', 'other') DEFAULT 'other' COMMENT '文件类型枚举',
    storage_type VARCHAR(20) DEFAULT 'local' COMMENT '存储类型（local本地存储 cloud云存储）',
    bucket_name VARCHAR(100) DEFAULT '' COMMENT '存储桶名称（云存储时使用）',
    created_by BIGINT DEFAULT 0 COMMENT '创建者ID',
    updated_by BIGINT DEFAULT 0 COMMENT '更新者ID',
    status TINYINT(1) DEFAULT 1 COMMENT '状态（0禁用 1正常）',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',

    INDEX idx_original_name (original_name),
    INDEX idx_file_hash (file_hash),
    INDEX idx_file_type (file_type),
    INDEX idx_storage_type (storage_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件表';

-- 9. 字典类型表
CREATE TABLE IF NOT EXISTS th_sys_dict_type (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '字典类型ID',
    name VARCHAR(100) NOT NULL COMMENT '字典名称',
    code VARCHAR(100) NOT NULL UNIQUE COMMENT '字典编码',
    description VARCHAR(500) DEFAULT '' COMMENT '字典描述',
    status TINYINT(1) DEFAULT 1 COMMENT '状态（0禁用 1正常）',
    sort INT(11) DEFAULT 100 COMMENT '排序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',

    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_sort (sort),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典类型表';

-- 10. 字典数据表
CREATE TABLE IF NOT EXISTS th_sys_dict_data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '字典数据ID',
    dict_type_id BIGINT NOT NULL COMMENT '字典类型ID',
    label VARCHAR(100) NOT NULL COMMENT '字典标签',
    value VARCHAR(255) NOT NULL COMMENT '字典值',
    sort INT(11) DEFAULT 100 COMMENT '排序',
    status TINYINT(1) DEFAULT 1 COMMENT '状态（0禁用 1正常）',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',

    INDEX idx_dict_type_id (dict_type_id),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典数据表';


-- =====================================================
-- 字典初始化数据
-- =====================================================
-- 字典类型
INSERT INTO `th_sys_dict_type` (`name`, `code`, `description`, `status`, `sort`) VALUES
('性别', 'sys_gender', '系统性别枚举', 1, 10),
('状态', 'sys_status', '通用状态枚举', 1, 20),
('是/否', 'sys_yes_no', '是/否枚举', 1, 30),
('通知类型', 'sys_notice_type', '通知消息类型', 1, 40);
-- 字典数据 - 性别
INSERT INTO `th_sys_dict_data` (`dict_type_id`, `label`, `value`, `sort`, `status`) VALUES
(1, '未知', '0', 10, 1),
(1, '男', '1', 20, 1),
(1, '女', '2', 30, 1);

-- 字典数据 - 状态
INSERT INTO `th_sys_dict_data` (`dict_type_id`, `label`, `value`, `sort`, `status`) VALUES
(2, '禁用', '0', 10, 1),
(2, '正常', '1', 20, 1);

-- 字典数据 - 是/否
INSERT INTO `th_sys_dict_data` (`dict_type_id`, `label`, `value`, `sort`, `status`) VALUES
(3, '否', '0', 10, 1),
(3, '是', '1', 20, 1);

-- 字典数据 - 通知类型
INSERT INTO `th_sys_dict_data` (`dict_type_id`, `label`, `value`, `sort`, `status`) VALUES
(5, '系统通知', 'system', 10, 1),
(5, '活动通知', 'activity', 20, 1),
(5, '订单通知', 'order', 30, 1),
(5, '物流通知', 'delivery', 40, 1);

-- =====================================================
-- 11. 配置表
-- =====================================================
CREATE TABLE IF NOT EXISTS `th_sys_config` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '配置ID',
    `name` VARCHAR(100) NOT NULL COMMENT '配置名称',
    `key` VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键名',
    `value` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置值',
    `type` ENUM('text', 'number', 'boolean', 'select', 'radio', 'checkbox', 'textarea', 'json', 'image') DEFAULT 'text' COMMENT '配置类型（text文本 number数字 boolean布尔 select选择 radio单选 checkbox复选 textarea多行文本 json JSON image图片 file文件）',
    `options` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '选项配置（JSON格式，用于select/radio/checkbox）',
    `group` VARCHAR(50) NOT NULL COMMENT '配置分组',
    `description` VARCHAR(500) DEFAULT '' COMMENT '配置描述',
    `sort` INT(11) DEFAULT 100 COMMENT '排序',
    `status` TINYINT(1) DEFAULT 1 COMMENT '状态（0禁用 1正常）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted` BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',

    INDEX idx_key (`key`),
    INDEX idx_group (`group`),
    INDEX idx_type (`type`),
    INDEX idx_sort (`sort`),
    INDEX idx_status (`status`),
    INDEX idx_deleted (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- =====================================================
-- 配置表初始化数据
-- =====================================================
INSERT INTO `th_sys_config` (`name`, `key`, `value`, `type`, `options`, `group`, `description`, `sort`, `status`) VALUES
-- 基础配置
('网站名称', 'site_name', 'The Admin', 'text', '', 'basic', '网站显示名称', 10, 1),
('网站Logo', 'site_logo', '', 'text', '', 'basic', '网站Logo地址', 20, 1),
('网站描述', 'site_description', '后台管理系统', 'textarea', '', 'basic', '网站描述信息', 30, 1),
('版权信息', 'copyright', '© 2024 The Admin. All Rights Reserved.', 'text', '', 'basic', '底部版权信息', 40, 1),

-- 上传配置
('允许上传格式', 'upload_allowed_ext', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip', 'text', '', 'upload', '允许上传的文件格式，多个用逗号分隔', 10, 1),
('最大上传大小(MB)', 'upload_max_size', '10', 'number', '', 'upload', '单文件最大上传大小，单位MB', 20, 1),

-- 安全配置
('登录失败锁定次数', 'login_max_attempts', '5', 'number', '', 'security', '连续登录失败次数，达到后锁定账户', 10, 1),
('登录锁定时间(分钟)', 'login_lock_minutes', '30', 'number', '', 'security', '账户锁定时长，单位分钟', 20, 1),
('Token有效期(小时)', 'token_expire_hours', '24', 'number', '', 'security', 'JWT Token有效期，单位小时', 30, 1),
('刷新Token有效期(天)', 'refresh_token_expire_days', '7', 'number', '', 'security', '刷新Token有效期，单位天', 40, 1),

-- 邮件配置
('SMTP服务器', 'smtp_host', '', 'text', '', 'email', '邮件发送服务器地址', 10, 1),
('SMTP端口', 'smtp_port', '465', 'number', '', 'email', '邮件发送端口', 20, 1),
('SMTP用户名', 'smtp_username', '', 'text', '', 'email', '邮件发送用户名', 30, 1),
('SMTP密码', 'smtp_password', '', 'text', '', 'email', '邮件发送密码', 40, 1),
('发件人名称', 'smtp_from_name', 'The Admin', 'text', '', 'email', '发件人显示名称', 50, 1),
('是否启用SSL', 'smtp_ssl', '1', 'radio', '{"0":"否","1":"是"}', 'email', '是否启用SSL加密', 60, 1);

-- =====================================================
-- 12. 登录日志表
-- =====================================================
CREATE TABLE IF NOT EXISTS `th_sys_log_login` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '日志ID',
    `admin_id` BIGINT NOT NULL COMMENT '管理员ID',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `ip` VARCHAR(50) DEFAULT '' COMMENT '登录IP',
    `user_agent` VARCHAR(500) DEFAULT '' COMMENT 'User-Agent',
    `location` VARCHAR(200) DEFAULT '' COMMENT '登录地点',
    `status` TINYINT(1) DEFAULT 1 COMMENT '登录状态（0失败 1成功）',
    `login_info` VARCHAR(255) DEFAULT '' COMMENT '登录信息（成功：登录成功 / 失败：失败原因）',
    `login_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',

    INDEX idx_admin_id (admin_id),
    INDEX idx_username (username),
    INDEX idx_ip (ip),
    INDEX idx_status (status),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录日志表';

-- =====================================================
-- 13. 操作日志表
-- =====================================================
CREATE TABLE IF NOT EXISTS `th_sys_log_operation` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '日志ID',
    `admin_id` BIGINT NOT NULL COMMENT '管理员ID',
    `username` VARCHAR(50) NOT NULL COMMENT '管理员名称',
    `module` VARCHAR(50) DEFAULT '' COMMENT '操作模块',
    `action` VARCHAR(50) DEFAULT '' COMMENT '操作类型',
    `description` VARCHAR(500) DEFAULT '' COMMENT '操作描述',
    `request_method` VARCHAR(10) DEFAULT '' COMMENT '请求方法',
    `request_url` VARCHAR(500) DEFAULT '' COMMENT '请求URL',
    `request_params` VARCHAR(500) DEFAULT NULL COMMENT '请求参数',
    `response_code` INT DEFAULT 20000 COMMENT '响应状态码',
    `response_msg` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '响应消息',
    `http_status` INT DEFAULT 200 COMMENT 'HTTP状态码',
    `cost_time` DECIMAL(10,3) DEFAULT 0 COMMENT '消耗时间（秒）',
    `ip` VARCHAR(50) DEFAULT '' COMMENT '操作IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',

    INDEX idx_admin_id (admin_id),
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_ip (ip),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- =====================================================
-- 初始化角色、管理员与权限数据
-- =====================================================
INSERT INTO `th_sys_role` (`id`, `code`, `name`, `description`, `status`, `sort`, `deleted`) VALUES
(1, 'R_SUPER', '超级管理员', '系统超级管理员，拥有所有权限', 1, 1, 0),
(2, 'R_SYSTEM', '系统管理员', '系统管理员，拥有系统管理核心权限', 1, 2, 0)
ON DUPLICATE KEY UPDATE
`name` = VALUES(`name`),
`description` = VALUES(`description`),
`status` = VALUES(`status`),
`sort` = VALUES(`sort`),
`deleted` = VALUES(`deleted`);

INSERT INTO `th_sys_admin` (`id`, `username`, `password`, `nickname`, `status`, `deleted`) VALUES
(1, 'admin', '$2y$10$M0KKw2uuChaAt0GQmvtXQeQtUs6WoqKWJXwUSZeSmJ/QWHBO7Jzz.', '超级管理员', 1, 0),
(2, 'system', '$2y$10$M0KKw2uuChaAt0GQmvtXQeQtUs6WoqKWJXwUSZeSmJ/QWHBO7Jzz.', '系统管理员', 1, 0)
ON DUPLICATE KEY UPDATE
`password` = VALUES(`password`),
`nickname` = VALUES(`nickname`),
`status` = VALUES(`status`),
`deleted` = VALUES(`deleted`);

INSERT INTO `th_sys_admin_role` (`admin_id`, `role_id`) VALUES
(1, 1),
(2, 2)
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

INSERT INTO `th_sys_permission` (`id`, `code`, `name`, `resource`, `action`, `description`, `status`, `sort`, `deleted`) VALUES
(1, 'sys:admin:page', '管理员列表', 'admin', 'page', '查看管理员列表', 1, 100, 0),
(2, 'sys:admin:create', '创建管理员', 'admin', 'create', '创建新管理员', 1, 101, 0),
(3, 'sys:admin:view', '查看管理员', 'admin', 'view', '查看管理员详情', 1, 102, 0),
(4, 'sys:admin:update', '更新管理员', 'admin', 'update', '更新管理员信息', 1, 103, 0),
(5, 'sys:admin:assign-role', '分配管理员角色', 'admin', 'assign-role', '为管理员分配角色', 1, 104, 0),
(6, 'sys:admin:delete', '删除管理员', 'admin', 'delete', '删除管理员', 1, 105, 0),
(7, 'sys:role:page', '角色列表', 'role', 'page', '查看角色列表', 1, 200, 0),
(8, 'sys:role:create', '创建角色', 'role', 'create', '创建新角色', 1, 201, 0),
(9, 'sys:role:view', '查看角色', 'role', 'view', '查看角色详情', 1, 202, 0),
(10, 'sys:role:update', '更新角色', 'role', 'update', '更新角色信息', 1, 203, 0),
(11, 'sys:role:assign-permission', '分配角色权限', 'role', 'assign-permission', '为角色分配权限', 1, 204, 0),
(12, 'sys:role:assign-menu', '分配角色菜单', 'role', 'assign-menu', '为角色分配菜单', 1, 205, 0),
(13, 'sys:role:delete', '删除角色', 'role', 'delete', '删除角色', 1, 206, 0),
(14, 'sys:permission:page', '权限列表', 'permission', 'page', '查看权限列表', 1, 300, 0),
(15, 'sys:permission:create', '创建权限', 'permission', 'create', '创建新权限', 1, 301, 0),
(16, 'sys:permission:view', '查看权限', 'permission', 'view', '查看权限详情', 1, 302, 0),
(17, 'sys:permission:update', '更新权限', 'permission', 'update', '更新权限信息', 1, 303, 0),
(18, 'sys:permission:delete', '删除权限', 'permission', 'delete', '删除权限', 1, 304, 0),
(19, 'sys:menu:page', '菜单列表', 'menu', 'page', '查看菜单列表', 1, 400, 0),
(20, 'sys:menu:create', '创建菜单', 'menu', 'create', '创建新菜单', 1, 401, 0),
(21, 'sys:menu:view', '查看菜单', 'menu', 'view', '查看菜单详情', 1, 402, 0),
(22, 'sys:menu:update', '更新菜单', 'menu', 'update', '更新菜单信息', 1, 403, 0),
(23, 'sys:menu:sort', '菜单排序', 'menu', 'sort', '调整菜单排序', 1, 404, 0),
(24, 'sys:menu:delete', '删除菜单', 'menu', 'delete', '删除菜单', 1, 405, 0),
(25, 'sys:file:list', '文件列表', 'file', 'list', '查看文件列表', 1, 500, 0),
(26, 'sys:file:create', '创建文件', 'file', 'create', '上传或创建文件', 1, 501, 0),
(27, 'sys:file:update', '编辑文件', 'file', 'update', '编辑文件信息', 1, 502, 0),
(28, 'sys:file:delete', '删除文件', 'file', 'delete', '删除文件', 1, 503, 0),
(29, 'sys:dict:type:page', '字典列表', 'dict-type', 'page', '查看字典列表', 1, 600, 0),
(30, 'sys:dict:type:create', '创建字典', 'dict-type', 'create', '创建新字典', 1, 601, 0),
(31, 'sys:dict:type:update', '编辑字典', 'dict-type', 'update', '编辑字典信息', 1, 602, 0),
(32, 'sys:dict:type:delete', '删除字典', 'dict-type', 'delete', '删除字典', 1, 603, 0),
(33, 'sys:config:page', '配置列表', 'config', 'page', '查看配置列表', 1, 700, 0),
(34, 'sys:config:create', '创建配置', 'config', 'create', '创建新配置', 1, 701, 0),
(35, 'sys:config:update', '编辑配置', 'config', 'update', '编辑配置信息', 1, 702, 0),
(36, 'sys:config:delete', '删除配置', 'config', 'delete', '删除配置', 1, 703, 0),
(37, 'sys:log:page', '日志列表', 'log', 'page', '查看日志列表', 1, 800, 0),
(38, 'sys:log:create', '创建日志', 'log', 'create', '创建日志记录', 1, 801, 0),
(39, 'sys:log:update', '编辑日志', 'log', 'update', '编辑日志信息', 1, 802, 0),
(40, 'sys:log:delete', '删除日志', 'log', 'delete', '删除日志', 1, 803, 0)
ON DUPLICATE KEY UPDATE
`name` = VALUES(`name`),
`resource` = VALUES(`resource`),
`action` = VALUES(`action`),
`description` = VALUES(`description`),
`status` = VALUES(`status`),
`sort` = VALUES(`sort`),
`deleted` = VALUES(`deleted`);

INSERT INTO `th_sys_role_permission` (`role_id`, `permission_id`) VALUES
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6),
(2, 7), (2, 8), (2, 9), (2, 10), (2, 11), (2, 12), (2, 13),
(2, 14), (2, 15), (2, 16), (2, 17), (2, 18),
(2, 19), (2, 20), (2, 21), (2, 22), (2, 23), (2, 24),
(2, 25), (2, 26), (2, 27), (2, 28),
(2, 29), (2, 30), (2, 31), (2, 32),
(2, 33), (2, 34), (2, 35), (2, 36),
(2, 37), (2, 38), (2, 39), (2, 40)
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- =====================================================
-- 菜单数据
-- 请在安装完成后执行 sql/menu_init.sql 填充菜单初始数据
-- menu_init.sql 中遵循 P0-1 按钮节点强规则：
-- 1. 按钮节点（type='B'）必须设置 permission，且挂在父级菜单页面（type='M'）下
-- 2. 页面节点（type='M' 或 'D'）不设置 permission
-- 3. 同级按钮节点 permission 不可重复
-- =====================================================
