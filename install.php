<?php

/**
 * TheAdmin权限系统安装脚本
 * 用于初始化数据库表和基础数据
 */

// 检查是否在命令行环境中运行
if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行环境中运行');
}

// 引入自动加载
require_once __DIR__ . '/../../vendor/autoload.php';

// 引入Webman框架
require_once __DIR__ . '/../../support/bootstrap.php';

use think\facade\Db;
use plugin\theadmin\app\common\ApiException;

class TheAdminInstaller
{
    /**
     * 执行安装
     */
    public function install()
    {
        echo "开始安装TheAdmin权限系统...\n";
        
        try {
            // 1. 检查数据库连接
            $this->checkDatabase();
            
            // 2. 执行数据库迁移
            $this->runMigrations();
            
            // 3. 插入初始数据
            $this->insertInitialData();
            
            echo "TheAdmin权限系统安装完成！\n";
            echo "默认管理员账号: admin\n";
            echo "默认管理员密码: admin123\n";
            echo "请及时修改默认密码！\n";
            
        } catch (Exception $e) {
            echo "安装失败: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * 检查数据库连接
     */
    private function checkDatabase()
    {
        echo "检查数据库连接...\n";
        
        try {
            // 配置数据库连接
            $config = require __DIR__ . '/config/think-orm.php';
            Db::setConfig($config);
            
            // 测试连接
            Db::query('SELECT 1');
            echo "数据库连接正常\n";
            
        } catch (Exception $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }

    /**
     * 执行数据库迁移
     */
    private function runMigrations()
    {
        echo "执行数据库迁移...\n";
        
        // 读取SQL文件
        $sqlFile = __DIR__ . '/sql/install.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL文件不存在: {$sqlFile}");
        }
        
        $sql = file_get_contents($sqlFile);
        if (empty($sql)) {
            throw new Exception("SQL文件内容为空");
        }
        
        // 分割SQL语句
        $statements = $this->parseSqlStatements($sql);
        
        // 执行SQL语句
        foreach ($statements as $statement) {
            if (empty(trim($statement))) {
                continue;
            }
            
            try {
                Db::execute($statement);
                echo "执行成功: " . $this->truncateStatement($statement) . "\n";
            } catch (Exception $e) {
                // 忽略表已存在的错误
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "执行失败: " . $e->getMessage() . "\n";
                    echo "SQL: " . $statement . "\n";
                    throw $e;
                }
            }
        }
        
        echo "数据库迁移完成\n";
    }

    /**
     * 解析SQL语句
     */
    private function parseSqlStatements($sql)
    {
        // 移除注释
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // 分割语句
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^(USE|CREATE DATABASE)/i', $stmt);
            }
        );
        
        return $statements;
    }

    /**
     * 截断SQL语句用于显示
     */
    private function truncateStatement($statement)
    {
        $statement = preg_replace('/\s+/', ' ', trim($statement));
        return strlen($statement) > 50 ? substr($statement, 0, 50) . '...' : $statement;
    }

    /**
     * 插入初始数据
     */
    private function insertInitialData()
    {
        echo "插入初始数据...\n";
        
        // 检查是否已有管理员数据
        $adminExists = Db::table('th_sys_admin')->where('username', 'admin')->find();
        if ($adminExists) {
            echo "管理员数据已存在，跳过初始化\n";
            return;
        }
        
        Db::startTrans();
        try {
            // 1. 插入超级管理员
            $adminId = Db::table('th_sys_admin')->insertGetId([
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'nickname' => '超级管理员',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "创建超级管理员成功，ID: {$adminId}\n";
            
            // 2. 插入超级管理员角色
            $roleId = Db::table('th_sys_role')->insertGetId([
                'code' => 'super_admin',
                'name' => '超级管理员',
                'description' => '系统超级管理员，拥有所有权限',
                'status' => 1,
                'sort' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "创建超级管理员角色成功，ID: {$roleId}\n";
            
            // 3. 关联管理员和角色
            Db::table('th_sys_admin_role')->insert([
                'admin_id' => $adminId,
                'role_id' => $roleId
            ]);
            echo "管理员角色关联成功\n";
            
            // 4. 插入基础权限
            $permissions = $this->getInitialPermissions();
            Db::table('th_sys_permission')->insertAll($permissions);
            echo "插入基础权限成功，共" . count($permissions) . "个\n";
            
            // 5. 为超级管理员角色分配所有权限
            $permissionIds = range(1, count($permissions));
            $rolePermissions = [];
            foreach ($permissionIds as $permissionId) {
                $rolePermissions[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
            Db::table('th_sys_role_permission')->insertAll($rolePermissions);
            echo "角色权限分配成功\n";
            
            // 6. 插入基础菜单
            $menus = $this->getInitialMenus();
            Db::table('th_sys_menu')->insertAll($menus);
            echo "插入基础菜单成功，共" . count($menus) . "个\n";
            
            // 7. 为超级管理员角色分配所有菜单
            $menuIds = range(1, count($menus));
            $roleMenus = [];
            foreach ($menuIds as $menuId) {
                $roleMenus[] = ['role_id' => $roleId, 'menu_id' => $menuId];
            }
            Db::table('th_sys_role_menu')->insertAll($roleMenus);
            echo "角色菜单分配成功\n";
            
            Db::commit();
            echo "初始数据插入完成\n";
            
        } catch (Exception $e) {
            Db::rollback();
            throw new Exception("插入初始数据失败: " . $e->getMessage());
        }
    }

    /**
     * 获取初始权限数据
     */
    private function getInitialPermissions()
    {
        $now = date('Y-m-d H:i:s');
        
        return [
            ['code' => 'admin.list', 'name' => '管理员列表', 'resource' => 'admin', 'action' => 'list', 'description' => '查看管理员列表', 'status' => 1, 'sort' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin.create', 'name' => '创建管理员', 'resource' => 'admin', 'action' => 'create', 'description' => '创建新管理员', 'status' => 1, 'sort' => 101, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin.update', 'name' => '更新管理员', 'resource' => 'admin', 'action' => 'update', 'description' => '更新管理员信息', 'status' => 1, 'sort' => 102, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin.delete', 'name' => '删除管理员', 'resource' => 'admin', 'action' => 'delete', 'description' => '删除管理员', 'status' => 1, 'sort' => 103, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'role.list', 'name' => '角色列表', 'resource' => 'role', 'action' => 'list', 'description' => '查看角色列表', 'status' => 1, 'sort' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'role.create', 'name' => '创建角色', 'resource' => 'role', 'action' => 'create', 'description' => '创建新角色', 'status' => 1, 'sort' => 201, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'role.update', 'name' => '更新角色', 'resource' => 'role', 'action' => 'update', 'description' => '更新角色信息', 'status' => 1, 'sort' => 202, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'role.delete', 'name' => '删除角色', 'resource' => 'role', 'action' => 'delete', 'description' => '删除角色', 'status' => 1, 'sort' => 203, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'permission.list', 'name' => '权限列表', 'resource' => 'permission', 'action' => 'list', 'description' => '查看权限列表', 'status' => 1, 'sort' => 300, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'permission.create', 'name' => '创建权限', 'resource' => 'permission', 'action' => 'create', 'description' => '创建新权限', 'status' => 1, 'sort' => 301, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'permission.update', 'name' => '更新权限', 'resource' => 'permission', 'action' => 'update', 'description' => '更新权限信息', 'status' => 1, 'sort' => 302, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'permission.delete', 'name' => '删除权限', 'resource' => 'permission', 'action' => 'delete', 'description' => '删除权限', 'status' => 1, 'sort' => 303, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'menu.list', 'name' => '菜单列表', 'resource' => 'menu', 'action' => 'list', 'description' => '查看菜单列表', 'status' => 1, 'sort' => 400, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'menu.create', 'name' => '创建菜单', 'resource' => 'menu', 'action' => 'create', 'description' => '创建新菜单', 'status' => 1, 'sort' => 401, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'menu.update', 'name' => '更新菜单', 'resource' => 'menu', 'action' => 'update', 'description' => '更新菜单信息', 'status' => 1, 'sort' => 402, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'menu.delete', 'name' => '删除菜单', 'resource' => 'menu', 'action' => 'delete', 'description' => '删除菜单', 'status' => 1, 'sort' => 403, 'created_at' => $now, 'updated_at' => $now],
        ];
    }

    /**
     * 获取初始菜单数据
     */
    private function getInitialMenus()
    {
        $now = date('Y-m-d H:i:s');
        
        return [
            ['parent_id' => 0, 'name' => 'system', 'title' => '系统管理', 'icon' => 'Setting', 'path' => '/system', 'component' => '', 'menu_type' => 1, 'permission' => '', 'sort' => 100, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['parent_id' => 1, 'name' => 'admin', 'title' => '管理员管理', 'icon' => 'User', 'path' => '/system/admin', 'component' => 'system/admin/index', 'menu_type' => 2, 'permission' => 'admin.list', 'sort' => 101, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['parent_id' => 1, 'name' => 'role', 'title' => '角色管理', 'icon' => 'UserFilled', 'path' => '/system/role', 'component' => 'system/role/index', 'menu_type' => 2, 'permission' => 'role.list', 'sort' => 102, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['parent_id' => 1, 'name' => 'permission', 'title' => '权限管理', 'icon' => 'Lock', 'path' => '/system/permission', 'component' => 'system/permission/index', 'menu_type' => 2, 'permission' => 'permission.list', 'sort' => 103, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['parent_id' => 1, 'name' => 'menu', 'title' => '菜单管理', 'icon' => 'Menu', 'path' => '/system/menu', 'component' => 'system/menu/index', 'menu_type' => 2, 'permission' => 'menu.list', 'sort' => 104, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];
    }
}

// 执行安装
$installer = new TheAdminInstaller();
$installer->install();