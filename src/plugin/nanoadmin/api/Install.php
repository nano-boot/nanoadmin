<?php

namespace plugin\nanoadmin\api;

use support\Db;
use Throwable;

class Install
{

    /**
     * 数据库连接
     */
    protected static $connection = 'plugin.admin.mysql';

    // ─── Menu 操作 ───────────────────────────────────────────────────────────

    /**
     * 批量导入菜单
     *
     * @param array $menus 菜单配置数组，每个元素需包含 key/name
     * @return void
     */
    public static function importMenus(array $menus): void
    {
        foreach ($menus as $menu) {
            static::importMenu($menu);
        }
    }

    /**
     * 导入单条菜单
     */
    protected static function importMenu(array $menu, int $parentId = 0): void
    {
        $children = $menu['children'] ?? [];
        unset($menu['children']);

        $exist = static::menuModel()->where('name', $menu['name'] ?? '')->first();
        if ($exist) {
            $id = $exist->id;
        } else {
            $data = static::buildMenuData($menu, $parentId);
            $id = static::menuModel()->insertGetId($data);
        }

        foreach ($children as $child) {
            static::importMenu($child, $id);
        }
    }

    /**
     * 按 name 删除菜单及其所有子菜单
     */
    public static function deleteMenu(string $name): void
    {
        $menu = static::menuModel()->where('name', $name)->first();
        if (!$menu) {
            return;
        }

        // 递归收集所有子菜单 ID
        $ids = static::collectChildIds($menu->id);
        $ids[] = $menu->id;

        static::menuModel()->whereIn('id', $ids)->delete();
    }

    /**
     * 从菜单数组中提取指定字段值
     *
     * @param array $menus
     * @param string $column
     * @return array
     */
    public static function menuColumn(array $menus, string $column): array
    {
        $result = [];
        foreach ($menus as $menu) {
            if (isset($menu[$column])) {
                $result[] = $menu[$column];
            }
            if (!empty($menu['children'])) {
                $result = array_merge($result, static::menuColumn($menu['children'], $column));
            }
        }
        return $result;
    }

    // ─── 核心操作 ───────────────────────────────────────────────────────────

    /**
     * 安装
     *
     * @param string $version
     * @return void
     */
    public static function install(string $version): void
    {
        static::installSql();
        if ($menus = static::getMenus()) {
            static::importMenus($menus);
        }
    }

    /**
     * 卸载
     *
     * @param string $version
     * @return void
     */
    public static function uninstall(string $version): void
    {
        foreach (static::getMenus() as $menu) {
            static::deleteMenu($menu['name'] ?? '');
        }
        static::uninstallSql();
    }

    /**
     * 更新
     *
     * @param string $from_version
     * @param string $to_version
     * @param array|null $context
     * @return void
     */
    public static function update(string $from_version, string $to_version, ?array $context = null): void
    {
        if (isset($context['previous_menus'])) {
            static::removeUnnecessaryMenus($context['previous_menus']);
        }
        static::installSql();
        if ($menus = static::getMenus()) {
            static::importMenus($menus);
        }
        $update_file = __DIR__ . '/../update.php';
        if (is_file($update_file)) {
            include $update_file;
        }
    }

    /**
     * 更新前数据收集
     *
     * @param string $from_version
     * @param string $to_version
     * @return array
     */
    public static function beforeUpdate(string $from_version, string $to_version): array
    {
        return ['previous_menus' => static::getMenus()];
    }

    // ─── 菜单配置 ───────────────────────────────────────────────────────────

    /**
     * 获取菜单配置
     *
     * @return array
     */
    public static function getMenus(): array
    {
        clearstatcache();
        if (is_file($menu_file = __DIR__ . '/../config/menu.php')) {
            $menus = include $menu_file;
            return $menus ?: [];
        }
        return [];
    }

    /**
     * 删除不需要的菜单
     */
    public static function removeUnnecessaryMenus(array $previous_menus): void
    {
        $to_remove = array_diff(
            static::menuColumn($previous_menus, 'name'),
            static::menuColumn(static::getMenus(), 'name')
        );
        foreach ($to_remove as $name) {
            static::deleteMenu($name);
        }
    }

    // ─── SQL 操作 ───────────────────────────────────────────────────────────

    /**
     * 安装 SQL
     */
    protected static function installSql(): void
    {
        static::importSql(__DIR__ . '/../install.sql');
    }

    /**
     * 卸载 SQL
     */
    protected static function uninstallSql(): void
    {
        $uninstallSqlFile = __DIR__ . '/../uninstall.sql';
        if (is_file($uninstallSqlFile)) {
            static::importSql($uninstallSqlFile);
            return;
        }
        $installSqlFile = __DIR__ . '/../install.sql';
        if (!is_file($installSqlFile)) {
            return;
        }
        $installSql = file_get_contents($installSqlFile);
        preg_match_all('/CREATE TABLE `(.+?)`/si', $installSql, $matches);
        $dropSql = '';
        foreach ($matches[1] as $table) {
            $dropSql .= "DROP TABLE IF EXISTS `$table`;\n";
        }
        file_put_contents($uninstallSqlFile, $dropSql);
        static::importSql($uninstallSqlFile);
        unlink($uninstallSqlFile);
    }

    /**
     * 执行 SQL 文件
     */
    public static function importSql(string $mysqlDumpFile): void
    {
        if (!$mysqlDumpFile || !is_file($mysqlDumpFile)) {
            return;
        }
        foreach (explode(';', file_get_contents($mysqlDumpFile)) as $sql) {
            if ($sql = trim($sql)) {
                try {
                    Db::connection(static::$connection)->statement($sql);
                } catch (Throwable $e) {}
            }
        }
    }

    // ─── 内部工具 ───────────────────────────────────────────────────────────

    /**
     * 获取菜单模型实例（延迟加载，避免循环依赖）
     */
    protected static function menuModel(): \plugin\nanoadmin\app\model\Menu
    {
        return new \plugin\nanoadmin\app\model\Menu();
    }

    /**
     * 递归收集子菜单 ID
     */
    protected static function collectChildIds(int $parentId): array
    {
        $ids = [];
        $children = static::menuModel()->where('parent_id', $parentId)->get(['id']);
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, static::collectChildIds($child->id));
        }
        return $ids;
    }

    /**
     * 构建菜单数据
     */
    protected static function buildMenuData(array $menu, int $parentId): array
    {
        return [
            'parent_id' => $parentId,
            'name' => $menu['name'] ?? '',
            'title' => $menu['title'] ?? '',
            'path' => $menu['path'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'component' => $menu['component'] ?? '',
            'redirect' => $menu['redirect'] ?? '',
            'type' => $menu['type'] ?? 'M',
            'permission' => $menu['permission'] ?? '',
            'status' => $menu['status'] ?? 1,
            'hidden' => $menu['hidden'] ?? 0,
            'keepalive' => $menu['keepalive'] ?? 0,
            'sort' => $menu['sort'] ?? 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
    }
}