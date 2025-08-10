<?php

/**
 * 数据库初始化脚本
 * 执行权限系统相关表的创建和初始数据插入
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use think\facade\Db;

// 配置数据库连接
$config = require __DIR__ . '/../config/think-orm.php';

// 初始化数据库连接
\think\facade\Db::setConfig($config);

try {
    echo "开始执行数据库初始化...\n";
    
    // 读取SQL文件
    $sqlFile = __DIR__ . '/../sql/install.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL文件不存在: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    if (empty($sql)) {
        throw new Exception("SQL文件内容为空");
    }
    
    // 分割SQL语句
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    // 执行SQL语句
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            Db::execute($statement);
            echo "执行成功: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            // 忽略表已存在的错误
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "执行失败: " . $e->getMessage() . "\n";
                echo "SQL: " . $statement . "\n";
            }
        }
    }
    
    // 插入初始管理员数据（如果不存在）
    $adminExists = Db::table('th_sys_admin')->where('username', 'admin')->find();
    if (!$adminExists) {
        // 插入超级管理员
        $adminId = Db::table('th_sys_admin')->insertGetId([
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'nickname' => '超级管理员',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "创建超级管理员成功，ID: {$adminId}\n";
        
        // 检查并创建超级管理员角色
        $roleExists = Db::table('th_sys_role')->where('code', 'super_admin')->find();
        if (!$roleExists) {
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
            
            // 关联管理员和角色
            Db::table('th_sys_admin_role')->insert([
                'admin_id' => $adminId,
                'role_id' => $roleId
            ]);
            
            echo "管理员角色关联成功\n";
        }
    } else {
        echo "超级管理员已存在，跳过创建\n";
    }
    
    echo "数据库初始化完成！\n";
    echo "默认管理员账号: admin\n";
    echo "默认管理员密码: admin123\n";
    
} catch (Exception $e) {
    echo "数据库初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}