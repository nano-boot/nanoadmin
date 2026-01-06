<?php

/**
 * 创建文件表数据库迁移脚本
 */

use think\migration\Migrator;
use think\migration\db\Column;

class CreateFileTable extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        $this->createFileTable();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->table('th_sys_file')->drop()->save();
    }

    /**
     * 创建文件表
     */
    private function createFileTable()
    {
        $table = $this->table('th_sys_file', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '文件表'
        ]);

        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'comment' => '文件ID'
        ])
        ->addColumn('original_name', 'string', [
            'limit' => 255,
            'null' => false,
            'comment' => '原始文件名'
        ])
        ->addColumn('file_name', 'string', [
            'limit' => 255,
            'null' => false,
            'comment' => '纯文件名（不含路径，如 abc123.png）'
        ])
        ->addColumn('file_path', 'string', [
            'limit' => 500,
            'null' => false,
            'comment' => '完整存储路径（包含分类目录，如 images/2024/01/06/abc123.png）'
        ])
        ->addColumn('file_size', 'biginteger', [
            'default' => 0,
            'comment' => '文件大小（字节）'
        ])
        ->addColumn('file_ext', 'string', [
            'limit' => 20,
            'default' => '',
            'comment' => '文件扩展名'
        ])
        ->addColumn('mime_type', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => 'MIME类型'
        ])
        ->addColumn('file_hash', 'string', [
            'limit' => 128,
            'default' => '',
            'comment' => '文件哈希值（SHA256）'
        ])
        ->addColumn('storage_type', 'string', [
            'limit' => 20,
            'default' => 'local',
            'comment' => '存储类型（local本地存储 cloud云存储）'
        ])
        ->addColumn('bucket_name', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => '存储桶名称（云存储时使用）'
        ])
        ->addColumn('created_by', 'biginteger', [
            'default' => 0,
            'comment' => '创建者ID'
        ])
        ->addColumn('updated_by', 'biginteger', [
            'default' => 0,
            'comment' => '更新者ID'
        ])
        ->addColumn('download_count', 'biginteger', [
            'default' => 0,
            'comment' => '下载次数'
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
        ->addIndex(['original_name'])
        ->addIndex(['file_hash'])
        ->addIndex(['storage_type'])
        ->addIndex(['created_by'])
        ->addIndex(['status'])
        ->addIndex(['created_at'])
        ->addIndex(['deleted'])
        ->create();
    }
}
