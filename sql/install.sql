-- theadmin数据库建表语句（无外键约束版本）
-- 数据库: theadmin
-- 字符集: utf8mb4
-- 排序规则: utf8mb4_unicode_ci

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS theadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE danciya;

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
    `avatar` varchar(255) DEFAULT '' COMMENT '头像',
    `phone` varchar(20) DEFAULT '' COMMENT '手机号',
    `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
    `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
  
    UNIQUE KEY `idx_username` (`username`),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- 2. 角色表
CREATE TABLE IF NOT EXISTS th_sys_roles (
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
CREATE TABLE IF NOT EXISTS th_sys_permissions (
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
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT '菜单ID',
    parent_id BIGINT DEFAULT 0 COMMENT '父菜单ID，0为顶级菜单',
    name VARCHAR(50) NOT NULL COMMENT '菜单名称',
    title VARCHAR(50) NOT NULL COMMENT '菜单标题',
    icon VARCHAR(50) DEFAULT '' COMMENT '菜单图标',
    path VARCHAR(200) DEFAULT '' COMMENT '路由路径',
    component VARCHAR(200) DEFAULT '' COMMENT '组件路径',
    redirect VARCHAR(200) DEFAULT '' COMMENT '重定向路径',
    menu_type TINYINT(1) DEFAULT 1 COMMENT '菜单类型（1目录 2菜单 3按钮）',
    permission VARCHAR(100) DEFAULT '' COMMENT '权限标识',
    is_hidden BOOLEAN DEFAULT FALSE COMMENT '是否隐藏',
    is_cache BOOLEAN DEFAULT TRUE COMMENT '是否缓存',
    is_affix BOOLEAN DEFAULT FALSE COMMENT '是否固定在标签页',
    link_url VARCHAR(500) DEFAULT '' COMMENT '外链地址',
    `status` tinyint(1) DEFAULT '1' COMMENT '状态（0禁用 1正常）',
    `sort` int(11) DEFAULT 100 COMMENT '排序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '是否删除',
    
    INDEX idx_parent_id (parent_id),
    INDEX idx_path (path),
    INDEX idx_permission (permission),
    INDEX idx_sort (sort),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单表';

-- 5. 用户角色关联表
CREATE TABLE IF NOT EXISTS th_sys_admin_roles (
    admin_id BIGINT NOT NULL COMMENT '管理员ID',
    role_id BIGINT NOT NULL COMMENT '角色ID',
    PRIMARY KEY (admin_id, role_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色关联表';

-- 6. 角色权限关联表
CREATE TABLE IF NOT EXISTS th_sys_role_permissions (
    role_id BIGINT NOT NULL COMMENT '角色ID',
    permission_id BIGINT NOT NULL COMMENT '权限ID',
    PRIMARY KEY (role_id, permission_id),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- 7. 角色菜单关联表
CREATE TABLE IF NOT EXISTS th_sys_role_menus (
    role_id BIGINT NOT NULL COMMENT '角色ID',
    menu_id BIGINT NOT NULL COMMENT '菜单ID',
    
    PRIMARY KEY (role_id, menu_id),
    INDEX idx_role_id (role_id),
    INDEX idx_menu_id (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色菜单关联表';