<?php

/**
 * 简化的NanoAdmin权限系统安装脚本
 */

// 检查是否在命令行环境中运行
if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行环境中运行');
}

// 引入自动加载
require_once __DIR__ . '/../../vendor/autoload.php';

// 直接配置ThinkORM
$config = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => '127.0.0.1',
            'database' => 'nanoadmin',
            'username' => 'root',
            'password' => '123456',
            'hostport' => '3306',
            'params' => [\PDO::ATTR_TIMEOUT => 3],
            'charset' => 'utf8mb4',
            'prefix' => 'th_',
            'break_reconnect' => true,
        ],
    ],
];

// 初始化数据库连接
\think\facade\Db::setConfig($config);

try {
    echo "开始安装NanoAdmin权限系统...\n";
    
    // 测试数据库连接
    echo "检查数据库连接...\n";
    \think\facade\Db::query('SELECT 1');
    echo "数据库连接正常\n";
    
    // 读取并执行SQL文件
    echo "执行数据库迁移...\n";
    $sqlFile = __DIR__ . '/sql/install.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL文件不存在: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 分割SQL语句并执行
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^(USE|CREATE DATABASE)/i', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            \think\facade\Db::execute($statement);
            $shortStmt = strlen($statement) > 50 ? substr($statement, 0, 50) . '...' : $statement;
            echo "执行成功: " . preg_replace('/\s+/', ' ', $shortStmt) . "\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "执行失败: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 检查并插入初始数据
    echo "检查初始数据...\n";
    $adminExists = \think\facade\Db::table('th_sys_admin')->where('username', 'admin')->find();
    
    if (!$adminExists) {
        echo "插入初始数据...\n";
        
        // 插入超级管理员
        $adminId = \think\facade\Db::table('th_sys_admin')->insertGetId([
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'nickname' => '超级管理员',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "创建超级管理员成功，ID: {$adminId}\n";
        
        // 插入超级管理员角色
        $roleId = \think\facade\Db::table('th_sys_role')->insertGetId([
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
        \think\facade\Db::table('th_sys_admin_role')->insert([
            'admin_id' => $adminId,
            'role_id' => $roleId
        ]);
        echo "管理员角色关联成功\n";
    } else {
        echo "初始数据已存在，跳过插入\n";
    }
    
    echo "\nNanoAdmin权限系统安装完成！\n";
    echo "默认管理员账号: admin\n";
    echo "默认管理员密码: admin123\n";
    echo "请及时修改默认密码！\n";
    
} catch (Exception $e) {
    echo "安装失败: " . $e->getMessage() . "\n";
    exit(1);
}