-- theadmin 卸载脚本
-- 删除插件创建的所有表（按依赖关系顺序）

-- 1. 删除关联表（先删关联表，再删主表）
DROP TABLE IF EXISTS `th_sys_admin_role`;
DROP TABLE IF EXISTS `th_sys_role_permission`;
DROP TABLE IF EXISTS `th_sys_role_menu`;

-- 2. 删除业务表
DROP TABLE IF EXISTS `th_sys_user`;
DROP TABLE IF EXISTS `th_sys_admin`;
DROP TABLE IF EXISTS `th_sys_role`;
DROP TABLE IF EXISTS `th_sys_permission`;
DROP TABLE IF EXISTS `th_sys_menu`;
DROP TABLE IF EXISTS `th_sys_file`;
DROP TABLE IF EXISTS `th_sys_dict_data`;
DROP TABLE IF EXISTS `th_sys_dict_type`;
DROP TABLE IF EXISTS `th_sys_config`;
