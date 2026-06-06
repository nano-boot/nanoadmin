<?php

/**
 * 权限系统数据库迁移脚本
 * 创建管理员、角色、权限、菜单相关表
 */

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePermissionTables extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 创建管理员表
        $this->createAdminTable();
        
        // 创建角色表
        $this->createRoleTable();
        
        // 创建权限表
        $this->createPermissionTable();
        
        // 创建菜单表
        $this->createMenuTable();
        
        // 创建关联表
        $this->createRelationTables();
        
        // 插入初始数据
        $this->insertInitialData();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->table('th_sys_role_menu')->drop()->save();
        $this->table('th_sys_role_permission')->drop()->save();
        $this->table('th_sys_admin_role')->drop()->save();
        $this->table('th_sys_menu')->drop()->save();
        $this->table('th_sys_permission')->drop()->save();
        $this->table('th_sys_role')->drop()->save();
        $this->table('th_sys_admin')->drop()->save();
    }

    /**
     * 创建管理员表
     */
    private function createAdminTable()
    {
        $table = $this->table('th_sys_admin', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员表'
        ]);

        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'comment' => '管理员ID'
        ])
        ->addColumn('username', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '用户名'
        ])
        ->addColumn('password', 'string', [
            'limit' => 255,
            'null' => false,
            'comment' => '密码'
        ])
        ->addColumn('nickname', 'string', [
            'limit' => 50,
            'default' => '',
            'comment' => '昵称'
        ])
        ->addColumn('avatar', 'string', [
            'limit' => 255,
            'default' => '',
            'comment' => '头像'
        ])
        ->addColumn('phone', 'string', [
            'limit' => 20,
            'default' => '',
            'comment' => '手机号'
        ])
        ->addColumn('last_login_ip', 'string', [
            'limit' => 50,
            'null' => true,
            'comment' => '最后登录IP'
        ])
        ->addColumn('last_login_time', 'datetime', [
            'null' => true,
            'comment' => '最后登录时间'
        ])
        ->addColumn('status', 'boolean', [
            'default' => true,
            'comment' => '状态（0禁用 1正常）'
        ])
        ->addColumn('created_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => '创建时间'
        ])
        ->addColumn('updated_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP',
            'comment' => '更新时间'
        ])
        ->addColumn('deleted', 'boolean', [
            'default' => false,
            'comment' => '是否删除'
        ])
        ->addIndex(['username'], ['unique' => true])
        ->addIndex(['deleted'])
        ->create();
    }

    /**
     * 创建角色表
     */
    private function createRoleTable()
    {
        $table = $this->table('th_sys_role', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色表'
        ]);

        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'comment' => '角色ID'
        ])
        ->addColumn('code', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '角色代码'
        ])
        ->addColumn('name', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => '角色名称'
        ])
        ->addColumn('description', 'string', [
            'limit' => 500,
            'null' => true,
            'comment' => '角色描述'
        ])
        ->addColumn('status', 'boolean', [
            'default' => true,
            'comment' => '状态（0禁用 1正常）'
        ])
        ->addColumn('sort', 'integer', [
            'default' => 100,
            'comment' => '排序'
        ])
        ->addColumn('created_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => '创建时间'
        ])
        ->addColumn('updated_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP',
            'comment' => '更新时间'
        ])
        ->addColumn('deleted', 'boolean', [
            'default' => false,
            'comment' => '是否删除'
        ])
        ->addIndex(['code'], ['unique' => true])
        ->addIndex(['status'])
        ->addIndex(['sort'])
        ->addIndex(['deleted'])
        ->create();
    }

    /**
     * 创建权限表
     */
    private function createPermissionTable()
    {
        $table = $this->table('th_sys_permission', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '权限表'
        ]);

        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'comment' => '权限ID'
        ])
        ->addColumn('code', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => '权限代码'
        ])
        ->addColumn('name', 'string', [
            'limit' => 100,
            'null' => false,
            'comment' => '权限名称'
        ])
        ->addColumn('resource', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '资源类型'
        ])
        ->addColumn('action', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '操作类型'
        ])
        ->addColumn('description', 'string', [
            'limit' => 500,
            'null' => true,
            'comment' => '权限描述'
        ])
        ->addColumn('status', 'boolean', [
            'default' => true,
            'comment' => '状态（0禁用 1正常）'
        ])
        ->addColumn('sort', 'integer', [
            'default' => 100,
            'comment' => '排序'
        ])
        ->addColumn('created_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => '创建时间'
        ])
        ->addColumn('updated_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP',
            'comment' => '更新时间'
        ])
        ->addColumn('deleted', 'boolean', [
            'default' => false,
            'comment' => '是否删除'
        ])
        ->addIndex(['code'], ['unique' => true])
        ->addIndex(['resource', 'action'])
        ->addIndex(['status'])
        ->addIndex(['deleted'])
        ->create();
    }

    /**
     * 创建菜单表
     */
    private function createMenuTable()
    {
        $table = $this->table('th_sys_menu', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '菜单表'
        ]);

        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'comment' => '菜单ID'
        ])
        ->addColumn('parent_id', 'biginteger', [
            'default' => 0,
            'comment' => '父菜单ID，0为顶级菜单'
        ])
        ->addColumn('name', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '菜单名称'
        ])
        ->addColumn('title', 'string', [
            'limit' => 50,
            'null' => false,
            'comment' => '菜单标题'
        ])
        ->addColumn('icon', 'string', [
            'limit' => 50,
            'default' => '',
            'comment' => '菜单图标'
        ])
        ->addColumn('path', 'string', [
            'limit' => 200,
            'default' => '',
            'comment' => '路由路径'
        ])
        ->addColumn('component', 'string', [
            'limit' => 200,
            'default' => '',
            'comment' => '组件路径'
        ])
        ->addColumn('redirect', 'string', [
            'limit' => 200,
            'default' => '',
            'comment' => '重定向路径'
        ])
        ->addColumn('menu_type', 'integer', [
            'limit' => 1,
            'default' => 1,
            'comment' => '菜单类型（1目录 2菜单 3按钮）'
        ])
        ->addColumn('permission', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => '权限标识'
        ])
        ->addColumn('is_hidden', 'boolean', [
            'default' => false,
            'comment' => '是否隐藏'
        ])
        ->addColumn('is_cache', 'boolean', [
            'default' => true,
            'comment' => '是否缓存'
        ])
        ->addColumn('is_affix', 'boolean', [
            'default' => false,
            'comment' => '是否固定在标签页'
        ])
        ->addColumn('link_url', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '外链地址'
        ])
        ->addColumn('status', 'boolean', [
            'default' => true,
            'comment' => '状态（0禁用 1正常）'
        ])
        ->addColumn('sort', 'integer', [
            'default' => 100,
            'comment' => '排序'
        ])
        ->addColumn('created_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => '创建时间'
        ])
        ->addColumn('updated_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP',
            'comment' => '更新时间'
        ])
        ->addColumn('deleted', 'boolean', [
            'default' => false,
            'comment' => '是否删除'
        ])
        ->addIndex(['parent_id'])
        ->addIndex(['path'])
        ->addIndex(['permission'])
        ->addIndex(['sort'])
        ->addIndex(['deleted'])
        ->create();
    }

    /**
     * 创建关联表
     */
    private function createRelationTables()
    {
        // 管理员角色关联表
        $adminRoleTable = $this->table('th_sys_admin_role', [
            'id' => false,
            'primary_key' => ['admin_id', 'role_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '管理员角色关联表'
        ]);

        $adminRoleTable->addColumn('admin_id', 'biginteger', [
            'null' => false,
            'comment' => '管理员ID'
        ])
        ->addColumn('role_id', 'biginteger', [
            'null' => false,
            'comment' => '角色ID'
        ])
        ->addIndex(['admin_id'])
        ->addIndex(['role_id'])
        ->create();

        // 角色权限关联表
        $rolePermissionTable = $this->table('th_sys_role_permission', [
            'id' => false,
            'primary_key' => ['role_id', 'permission_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色权限关联表'
        ]);

        $rolePermissionTable->addColumn('role_id', 'biginteger', [
            'null' => false,
            'comment' => '角色ID'
        ])
        ->addColumn('permission_id', 'biginteger', [
            'null' => false,
            'comment' => '权限ID'
        ])
        ->addIndex(['role_id'])
        ->addIndex(['permission_id'])
        ->create();

        // 角色菜单关联表
        $roleMenuTable = $this->table('th_sys_role_menu', [
            'id' => false,
            'primary_key' => ['role_id', 'menu_id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '角色菜单关联表'
        ]);

        $roleMenuTable->addColumn('role_id', 'biginteger', [
            'null' => false,
            'comment' => '角色ID'
        ])
        ->addColumn('menu_id', 'biginteger', [
            'null' => false,
            'comment' => '菜单ID'
        ])
        ->addIndex(['role_id'])
        ->addIndex(['menu_id'])
        ->create();
    }

    /**
     * 插入初始数据
     */
    private function insertInitialData()
    {
        // 插入超级管理员
        $this->table('th_sys_admin')->insert([
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'nickname' => '超级管理员',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ])->save();

        // 插入超级管理员角色
        $this->table('th_sys_role')->insert([
            'code' => 'super_admin',
            'name' => '超级管理员',
            'description' => '系统超级管理员，拥有所有权限',
            'status' => 1,
            'sort' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ])->save();

        // 关联超级管理员和角色
        $this->table('th_sys_admin_role')->insert([
            'admin_id' => 1,
            'role_id' => 1
        ])->save();

        // 插入基础权限
        $permissions = [
            ['code' => 'sys:admin:page', 'name' => '管理员列表', 'resource' => 'admin', 'action' => 'page', 'description' => '查看管理员列表'],
            ['code' => 'sys:admin:create', 'name' => '创建管理员', 'resource' => 'admin', 'action' => 'create', 'description' => '创建新管理员'],
            ['code' => 'sys:admin:update', 'name' => '更新管理员', 'resource' => 'admin', 'action' => 'update', 'description' => '更新管理员信息'],
            ['code' => 'sys:admin:delete', 'name' => '删除管理员', 'resource' => 'admin', 'action' => 'delete', 'description' => '删除管理员'],
            ['code' => 'sys:role:page', 'name' => '角色列表', 'resource' => 'role', 'action' => 'page', 'description' => '查看角色列表'],
            ['code' => 'sys:role:create', 'name' => '创建角色', 'resource' => 'role', 'action' => 'create', 'description' => '创建新角色'],
            ['code' => 'sys:role:update', 'name' => '更新角色', 'resource' => 'role', 'action' => 'update', 'description' => '更新角色信息'],
            ['code' => 'sys:role:delete', 'name' => '删除角色', 'resource' => 'role', 'action' => 'delete', 'description' => '删除角色'],
            ['code' => 'sys:permission:page', 'name' => '权限列表', 'resource' => 'permission', 'action' => 'page', 'description' => '查看权限列表'],
            ['code' => 'sys:permission:create', 'name' => '创建权限', 'resource' => 'permission', 'action' => 'create', 'description' => '创建新权限'],
            ['code' => 'sys:permission:update', 'name' => '更新权限', 'resource' => 'permission', 'action' => 'update', 'description' => '更新权限信息'],
            ['code' => 'sys:permission:delete', 'name' => '删除权限', 'resource' => 'permission', 'action' => 'delete', 'description' => '删除权限'],
            ['code' => 'sys:menu:page', 'name' => '菜单列表', 'resource' => 'menu', 'action' => 'page', 'description' => '查看菜单列表'],
            ['code' => 'sys:menu:create', 'name' => '创建菜单', 'resource' => 'menu', 'action' => 'create', 'description' => '创建新菜单'],
            ['code' => 'sys:menu:update', 'name' => '更新菜单', 'resource' => 'menu', 'action' => 'update', 'description' => '更新菜单信息'],
            ['code' => 'sys:menu:delete', 'name' => '删除菜单', 'resource' => 'menu', 'action' => 'delete', 'description' => '删除菜单'],
        ];

        foreach ($permissions as $permission) {
            $permission['status'] = 1;
            $permission['sort'] = 100;
            $permission['created_at'] = date('Y-m-d H:i:s');
            $permission['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->table('th_sys_permission')->insert($permissions)->save();

        // 为超级管理员角色分配所有权限
        $rolePermissions = [];
        for ($i = 1; $i <= 16; $i++) {
            $rolePermissions[] = ['role_id' => 1, 'permission_id' => $i];
        }
        $this->table('th_sys_role_permission')->insert($rolePermissions)->save();

        // 插入基础菜单
        $menus = [
            [
                'parent_id' => 0,
                'name' => 'system',
                'title' => '系统管理',
                'icon' => 'Setting',
                'path' => '/system',
                'component' => '',
                'menu_type' => 1,
                'sort' => 100
            ],
            [
                'parent_id' => 1,
                'name' => 'admin',
                'title' => '管理员管理',
                'icon' => 'User',
                'path' => '/system/admin',
                'component' => 'system/admin/index',
                'menu_type' => 2,
                'permission' => 'sys:admin:page',
                'sort' => 101
            ],
            [
                'parent_id' => 1,
                'name' => 'role',
                'title' => '角色管理',
                'icon' => 'UserFilled',
                'path' => '/system/role',
                'component' => 'system/role/index',
                'menu_type' => 2,
                'permission' => 'sys:role:page',
                'sort' => 102
            ],
            [
                'parent_id' => 1,
                'name' => 'permission',
                'title' => '权限管理',
                'icon' => 'Lock',
                'path' => '/system/permission',
                'component' => 'system/permission/index',
                'menu_type' => 2,
                'permission' => 'sys:permission:page',
                'sort' => 103
            ],
            [
                'parent_id' => 1,
                'name' => 'menu',
                'title' => '菜单管理',
                'icon' => 'Menu',
                'path' => '/system/menu',
                'component' => 'system/menu/index',
                'menu_type' => 2,
                'permission' => 'sys:menu:page',
                'sort' => 104
            ]
        ];

        foreach ($menus as $menu) {
            $menu['status'] = 1;
            $menu['created_at'] = date('Y-m-d H:i:s');
            $menu['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->table('th_sys_menu')->insert($menus)->save();

        // 为超级管理员角色分配所有菜单
        $roleMenus = [];
        for ($i = 1; $i <= 5; $i++) {
            $roleMenus[] = ['role_id' => 1, 'menu_id' => $i];
        }
        $this->table('th_sys_role_menu')->insert($roleMenus)->save();
    }
}