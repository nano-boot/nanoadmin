<?php

/**
 * 添加文件类型字段到文件表数据库迁移脚本
 */

use think\migration\Migrator;
use think\migration\db\Column;

class AddFileTypeToFileTable extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 添加 file_type 列
        $this->table('th_sys_file')
            ->addColumn('file_type', 'enum', [
                'values' => ['image', 'video', 'document', 'audio', 'archive', 'other'],
                'default' => 'other',
                'comment' => '文件类型枚举',
                'after' => 'file_hash'
            ])
            ->update();

        // 添加索引
        $this->table('th_sys_file')->addIndex(['file_type'])->save();

        // 回填现有数据的 file_type
        $this->backfillFileTypes();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 移除索引
        $this->table('th_sys_file')->removeIndex(['file_type'])->save();

        // 移除列
        $this->table('th_sys_file')->removeColumn('file_type')->update();
    }

    /**
     * 回填现有文件的 file_type
     */
    private function backfillFileTypes()
    {
        $sql = "
            UPDATE th_sys_file
            SET file_type = CASE
              WHEN LOWER(file_ext) IN ('jpg','jpeg','png','gif','bmp','webp','svg') THEN 'image'
              WHEN LOWER(file_ext) IN ('mp4','avi','mov','wmv','flv','mkv','webm') THEN 'video'
              WHEN LOWER(file_ext) IN ('pdf','doc','docx','xls','xlsx','ppt','pptx','txt') THEN 'document'
              WHEN LOWER(file_ext) IN ('mp3','wav','flac','aac','ogg') THEN 'audio'
              WHEN LOWER(file_ext) IN ('zip','rar','7z','tar','gz') THEN 'archive'
              ELSE 'other'
            END
            WHERE file_type IS NULL OR file_type = '';
        ";

        $this->execute($sql);
    }
}
