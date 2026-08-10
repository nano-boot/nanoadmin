-- 角色数据权限增强
-- 1. 角色表添加 data_scope 字段
ALTER TABLE `na_sys_role` ADD COLUMN `data_scope` tinyint(1) DEFAULT 1 COMMENT '数据权限范围（1全部数据 2本部门及下级 3本部门 4仅本人 5自定义部门）' AFTER `sort`;

-- 2. 管理员表添加 created_by 字段（支持"仅本人"数据权限）
ALTER TABLE `na_sys_admin` ADD COLUMN `created_by` BIGINT NOT NULL DEFAULT 0 COMMENT '创建人ID（0=系统创建）' AFTER `dept_id`;
ALTER TABLE `na_sys_admin` ADD INDEX `idx_created_by` (`created_by`);

-- 3. 创建角色数据权限部门关联表（用于自定义部门模式）
CREATE TABLE IF NOT EXISTS `na_sys_role_dept` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    `role_id` BIGINT NOT NULL COMMENT '角色ID',
    `dept_id` BIGINT NOT NULL COMMENT '部门ID',
    UNIQUE KEY `uk_role_dept` (`role_id`, `dept_id`),
    INDEX `idx_role_id` (`role_id`),
    INDEX `idx_dept_id` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色数据权限部门关联表';
