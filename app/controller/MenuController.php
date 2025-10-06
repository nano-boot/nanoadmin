<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\validator\MenuValidator;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\Menu;
use plugin\theadmin\app\service\MenuTransformService;
use plugin\theadmin\app\service\MenuSearchService;
 

/**
 * 菜单管理控制器
 * 实现菜单的增删改查、树形结构构建、搜索和分页功能
 */
class MenuController
{
    /**
     * 菜单模型实例
     * @var Menu
     */
    private Menu $menuModel;

    /**
     * 菜单数据转换服务
     * @var MenuTransformService
     */
    private MenuTransformService $transformService;

    /**
     * 菜单搜索服务
     * @var MenuSearchService
     */
    private MenuSearchService $searchService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->menuModel = new Menu();
        $this->transformService = new MenuTransformService();
        $this->searchService = new MenuSearchService();
        
        // 初始化验证器并自动验证请求参数
        new MenuValidator();
    }

    /**
     * 获取菜单列表（支持分页和搜索）
     * GET /api/menus
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            // 获取查询参数
            $params = [
                'page' => (int)$request->get('page', 1),
                'limit' => (int)$request->get('limit', 15),
                'keyword' => trim($request->get('keyword', '')),
                'status' => $request->get('status', ''),
                'type' => $request->get('type', ''),
                'parent_id' => $request->get('parent_id', '')
            ];

            // 构建查询条件
            $where = [];

            // 状态筛选
            if ($params['status'] !== '') {
                $where['status'] = (bool)$params['status'];
            }

            // 菜单类型筛选
            if ($params['type'] !== '') {
                $where['type'] = $params['type'];
            }

            // 父菜单筛选
            if ($params['parent_id'] !== '') {
                $where['parent_id'] = (int)$params['parent_id'];
            }

            // 关键词搜索（菜单名称或标题）
            if (!empty($params['keyword'])) {
                $where['name'] = ['like', '%' . $params['keyword'] . '%'];
                $where['title'] = ['like', '%' . $params['keyword'] . '%'];
            }

            // 获取分页数据
            $paginator = $this->menuModel->getListWithLevel($where, $params['page'], $params['limit']);

            // 格式化数据
            $list = [];
            foreach ($paginator->items() as $menu) {
                $list[] = $this->transformService->formatForApi($menu->toArray());
            }

            $result = [
                'list' => $list,
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'limit' => $paginator->listRows(),
                'pages' => $paginator->lastPage()
            ];

            return $this->success($result, '获取菜单列表成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取当前用户的树状路由
     * GET /menus/routes
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function route(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        if (empty($adminId)) {
            return $this->error(Code::UNAUTHORIZED, '未登录');
        }

        // 获取管理员可访问的菜单树并转换为前端路由格式
        $menuTree = $this->menuModel->getAdminMenuTree((int)$adminId);

        $routes = $this->transformService->toRouteConfigTree($menuTree);
        return R::data(['routes' => $routes]);
    }


    



    /**
     * 获取菜单树形结构
     * GET /admin/menu/tree
     * @param Request $request
     * @return Response
     */
    public function tree(Request $request): Response
    {
        var_dump('获取菜单树形结构');
        // 获取查询参数
        $parentId = (int)$request->get('parent_id', 0);
        $onlyEnabled = $request->get('only_enabled', 'true') === 'true';
        $keyword = trim($request->get('keyword', ''));
        if (!empty($keyword)) {
            // 使用搜索服务进行搜索
            $options = [
                'search_fields' => ['name', 'title', 'path'],
                'include_disabled' => !$onlyEnabled,
                'include_hidden' => true,
                'maintain_hierarchy' => true,
                'parent_id' => $parentId > 0 ? $parentId : null
            ];
            $tree = $this->searchService->searchMenus($keyword, $options);
        } else {
            // 获取菜单树
            $tree = $this->menuModel->getTree($parentId, $onlyEnabled);
        }

        $routes = $this->formatTreeForApi($tree);

        return R::data(['routes' => $routes]);
    }

    /**
     * 获取菜单详情
     * GET /api/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 查找菜单
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 转换为表单数据格式
            $formData = $this->transformService->toFormData($menu->toArray());

            return $this->success($formData, '获取菜单详情成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单详情失败：' . $e->getMessage());
        }
    }

    /**
     * 创建菜单
     * POST /api/menus
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            // 验证器已在构造函数中自动验证，直接获取验证后的数据
            $requestData = $request->all();
            var_dump('$requestData');
            // 数据类型转换和默认值设置
            $requestData['parent_id'] = (int)($requestData['parent_id'] ?? 0);
            $requestData['name'] = trim($requestData['name'] ?? '');
            $requestData['path'] = trim($requestData['path'] ?? '');
            $requestData['component'] = trim($requestData['component'] ?? '');
            $requestData['redirect'] = trim($requestData['redirect'] ?? '');
            $requestData['title'] = trim($requestData['title'] ?? '');
            $requestData['icon'] = trim($requestData['icon'] ?? '');
            $requestData['type'] = trim($requestData['type'] ?? 'D');
            $requestData['permission'] = trim($requestData['permission'] ?? '');
            $requestData['hidden'] = (bool)($requestData['hidden'] ?? false);
            $requestData['hide_tab'] = (bool)($requestData['hide_tab'] ?? false);
            $requestData['full_page'] = (bool)($requestData['full_page'] ?? false);
            $requestData['keep_alive'] = (bool)($requestData['keep_alive'] ?? true);
            $requestData['fixed_tab'] = (bool)($requestData['fixed_tab'] ?? false);
            $requestData['link_url'] = trim($requestData['link_url'] ?? '');
            $requestData['iframe'] = (bool)($requestData['iframe'] ?? false);
            $requestData['show_badge'] = (bool)($requestData['show_badge'] ?? false);
            $requestData['badge_text'] = trim($requestData['badge_text'] ?? '');
            $requestData['active_path'] = trim($requestData['active_path'] ?? '');
            $requestData['status'] = (bool)($requestData['status'] ?? true);
            $requestData['sort'] = (int)($requestData['sort'] ?? 100);


            // 检查父菜单是否存在（如果不是顶级菜单）
            if ($requestData['parent_id'] > 0) {
                $parentMenu = $this->menuModel->find($requestData['parent_id']);
                if (!$parentMenu) {
                    return $this->error(Code::MENU_NOT_FOUND, '父菜单不存在');
                }
                
                // 检查父菜单类型，按钮不能有子菜单
                if ($parentMenu->type === 'B') {
                    return $this->error(Code::PARAMETER_ERROR, '按钮类型菜单不能添加子菜单');
                }
            }

            // 检查同级菜单名称是否重复
            $existingMenu = $this->menuModel
                ->where('parent_id', $requestData['parent_id'])
                ->where('name', $requestData['name'])
                ->whereNull('deleted_at')
                ->first();
            
            if ($existingMenu) {
                return $this->error(Code::PARAMETER_ERROR, '同级菜单中已存在相同的路由名称');
            }

            // 检查路由路径是否重复（仅对菜单类型检查）
            if ($requestData['type'] === 'M' && !empty($requestData['path'])) {
                $existingPath = $this->menuModel
                    ->where('path', $requestData['path'])
                    ->where('type', 'M')
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($existingPath) {
                    return $this->error(Code::PARAMETER_ERROR, '路由路径已存在');
                }
            }

            // 准备数据库数据
            $dbData = [
                'parent_id' => $requestData['parent_id'],
                'name' => $requestData['name'],
                'path' => $requestData['path'],
                'component' => $requestData['component'],
                'redirect' => $requestData['redirect'],
                'title' => $requestData['title'],
                'icon' => $requestData['icon'],
                'type' => $requestData['type'],
                'permission' => $requestData['permission'],
                'hidden' => $requestData['hidden'] ? 1 : 0,
                'hide_tab' => $requestData['hide_tab'] ? 1 : 0,
                'full_page' => $requestData['full_page'] ? 1 : 0,
                'keep_alive' => $requestData['keep_alive'] ? 1 : 0,
                'fixed_tab' => $requestData['fixed_tab'] ? 1 : 0,
                'link_url' => $requestData['link_url'],
                'iframe' => $requestData['iframe'] ? 1 : 0,
                'show_badge' => $requestData['show_badge'] ? 1 : 0,
                'badge_text' => $requestData['badge_text'],
                'active_path' => $requestData['active_path'],
                'status' => $requestData['status'] ? 1 : 0,
                'sort' => $requestData['sort'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted' => false
            ];

            // 创建菜单
            $menu = $this->menuModel->create($dbData);
            
            if (!$menu) {
                return $this->error(Code::SYSTEM_ERROR, '创建菜单失败');
            }

            // 转换为API响应格式
            $formattedMenu = $this->transformService->formatForApi($menu->toArray());

            return $this->success($formattedMenu, '创建菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '创建菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 更新菜单
     * PUT /api/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 获取表单数据
            $formData = $this->getFormDataFromRequest($request);

            // 数据验证
            $validation = $this->transformService->validateMenuData($formData);
            if (!$validation['valid']) {
                return $this->error(Code::PARAMETER_ERROR, implode(', ', $validation['errors']));
            }

            // 转换为数据库格式
            $dbData = $this->transformService->fromFormData($formData);

            // 更新菜单
            $result = $this->menuModel->updateMenu($id, $dbData);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '更新菜单失败');
            }

            // 获取更新后的菜单数据
            $updatedMenu = $this->menuModel->find($id);
            $formattedData = $this->transformService->formatForApi($updatedMenu->toArray());

            return $this->success($formattedData, '更新菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '更新菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 删除菜单
     * DELETE /api/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 删除菜单
            $result = $this->menuModel->deleteMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '删除菜单失败');
            }

            return $this->success(null, '删除菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '删除菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 批量更新菜单排序
     * POST /api/menus/sort
     * @param Request $request
     * @return Response
     */
    public function sort(Request $request): Response
    {
        try {
            $sortData = $request->post('sort_data', []);
            
            if (!is_array($sortData) || empty($sortData)) {
                return $this->error(Code::PARAMETER_ERROR, '排序数据格式错误');
            }

            // 验证排序数据格式
            foreach ($sortData as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    return $this->error(Code::PARAMETER_ERROR, '排序数据格式错误');
                }

                if (!is_numeric($item['id']) || $item['id'] <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }

                if (isset($item['sort']) && (!is_numeric($item['sort']) || $item['sort'] < 0)) {
                    return $this->error(Code::PARAMETER_ERROR, '排序值必须为非负整数');
                }

                if (isset($item['parent_id']) && (!is_numeric($item['parent_id']) || $item['parent_id'] < 0)) {
                    return $this->error(Code::PARAMETER_ERROR, '父菜单ID必须为非负整数');
                }
            }

            // 批量更新排序
            $result = $this->menuModel->batchUpdateSort($sortData);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '批量更新排序失败');
            }

            return $this->success(null, '菜单排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '菜单排序失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单路径（面包屑）
     * GET /api/menus/{id}/path
     * @param Request $request
     * @return Response
     */
    public function path(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 获取菜单路径
            $path = $this->menuModel->getMenuPath($id);
            
            // 构建面包屑数据
            $breadcrumbs = $this->transformService->buildBreadcrumbData($path);

            return $this->success($breadcrumbs, '获取菜单路径成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单路径失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单选择器数据
     * GET /api/menus/selector
     * @param Request $request
     * @return Response
     */
    public function selector(Request $request): Response
    {
        try {
            $includeButtons = $request->get('include_buttons', 'false') === 'true';
            $onlyEnabled = $request->get('only_enabled', 'true') === 'true';

            // 获取菜单树
            $tree = $this->menuModel->getTree(0, $onlyEnabled);

            // 构建选择器数据
            $selectorData = $this->transformService->buildSelectorData($tree, $includeButtons);

            return $this->success($selectorData, '获取菜单选择器数据成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单选择器数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单统计信息
     * GET /api/menus/statistics
     * @param Request $request
     * @return Response
     */
    public function statistics(Request $request): Response
    {
        try {
            // 获取菜单树
            $tree = $this->menuModel->getTree(0, false);

            // 构建统计信息
            $statistics = $this->transformService->buildStatistics($tree);

            return $this->success($statistics, '获取菜单统计信息成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单统计信息失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单类型选项
     * GET /api/menus/types
     * @param Request $request
     * @return Response
     */
    public function types(Request $request): Response
    {
        try {
            $types = Menu::getMenuTypeOptions();
            return $this->success($types, '获取菜单类型选项成功');
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取菜单类型选项失败：' . $e->getMessage());
        }
    }

    /**
     * 从请求中获取表单数据
     * @param Request $request
     * @return array
     */
    private function getFormDataFromRequest(Request $request): array
    {
        return [
            'parent_id' => (int)$request->post('parent_id', 0),
            'name' => trim($request->post('name', '')),
            'title' => trim($request->post('title', '')),
            'icon' => trim($request->post('icon', '')),
            'path' => trim($request->post('path', '')),
            'component' => trim($request->post('component', '')),
            'redirect' => trim($request->post('redirect', '')),
            'type' => trim($request->post('type', Menu::TYPE_DIRECTORY)),
            'permission' => trim($request->post('permission', '')),
            'hidden' => (bool)$request->post('hidden', false),
            'cacheable' => (bool)$request->post('cacheable', true),
            'affix' => (bool)$request->post('affix', false),
            'full_page' => (bool)$request->post('full_page', false),
            'link_url' => trim($request->post('link_url', '')),
            'iframe' => (bool)$request->post('iframe', false),
            'show_badge' => (bool)$request->post('show_badge', false),
            'badge_text' => trim($request->post('badge_text', '')),
            'active_path' => trim($request->post('active_path', '')),
            'status' => (bool)$request->post('status', true),
            'sort' => (int)$request->post('sort', 100),
            'roles' => $request->post('roles', []),
            'auth_list' => $request->post('auth_list', [])
        ];
    }

    /**
     * 根据关键词过滤菜单树
     * @param array $tree
     * @param string $keyword
     * @return array
     */
    private function filterTreeByKeyword(array $tree, string $keyword): array
    {
        $filtered = [];

        foreach ($tree as $menu) {
            $match = false;

            // 检查当前菜单是否匹配
            if (stripos($menu['name'], $keyword) !== false || 
                stripos($menu['title'], $keyword) !== false) {
                $match = true;
            }

            // 递归过滤子菜单
            $filteredChildren = [];
            if (!empty($menu['children'])) {
                $filteredChildren = $this->filterTreeByKeyword($menu['children'], $keyword);
                if (!empty($filteredChildren)) {
                    $match = true;
                }
            }

            // 如果匹配，添加到结果中
            if ($match) {
                $menu['children'] = $filteredChildren;
                $filtered[] = $menu;
            }
        }

        return $filtered;
    }

    /**
     * 格式化菜单树用于API响应
     * @param array $tree
     * @return array
     */
    private function formatTreeForApi(array $tree): array
    {
        $formatted = [];

        foreach ($tree as $menu) {
            $item = $this->transformService->formatForApi($menu);

            // 递归格式化子菜单
            if (!empty($menu['children'])) {
                $item['children'] = $this->formatTreeForApi($menu['children']);
            }

            $formatted[] = $item;
        }

        return $formatted;
    }

    /**
     * 成功响应
     * @param mixed|null $data
     * @param string $message
     * @param int $httpCode
     * @return Response
     */
    private function success(mixed $data = null, string $message = '操作成功', int $httpCode = 200): Response
    {
        return R::ok($message,$data);
    }

    /**
     * 错误响应
     * @param Code|int $code
     * @param string $message
     * @return Response
     */
    private function error(Code|int $code, string $message): Response
    {
        if ($code instanceof Code) {
            return R::error($message, $code->value);
        } else {
            return R::error($message, $code);
        }
    }

    /**
     * 搜索菜单
     * GET /api/menus/search
     * @param Request $request
     * @return Response
     */
    public function search(Request $request): Response
    {
        try {
            $keyword = trim($request->get('keyword', ''));
            
            // 搜索选项
            $options = [
                'search_fields' => $request->get('search_fields', ['name', 'title', 'path']),
                'include_disabled' => $request->get('include_disabled', 'false') === 'true',
                'include_hidden' => $request->get('include_hidden', 'false') === 'true',
                'menu_types' => $request->get('menu_types', []),
                'maintain_hierarchy' => $request->get('maintain_hierarchy', 'true') === 'true',
                'parent_id' => $request->get('parent_id') !== null ? (int)$request->get('parent_id') : null
            ];

            // 执行搜索
            $results = $this->searchService->searchMenus($keyword, $options);

            // 格式化结果
            $formattedResults = [];
            foreach ($results as $menu) {
                $formattedResults[] = $this->transformService->formatForApi($menu);
            }

            return $this->success([
                'results' => $formattedResults,
                'keyword' => $keyword,
                'options' => $options,
                'total' => count($formattedResults)
            ], '菜单搜索成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '菜单搜索失败：' . $e->getMessage());
        }
    }

    /**
     * 高级搜索菜单
     * POST /api/menus/advanced-search
     * @param Request $request
     * @return Response
     */
    public function advancedSearch(Request $request): Response
    {
        try {
            // 获取搜索参数
            $searchParams = [
                'keyword' => trim($request->post('keyword', '')),
                'search_fields' => $request->post('search_fields', ['name', 'title', 'path']),
                'menu_types' => $request->post('menu_types', []),
                'status' => $request->post('status'),
                'hidden' => $request->post('hidden'),
                'parent_id' => $request->post('parent_id'),
                'permission' => trim($request->post('permission', '')),
                'path' => trim($request->post('path', '')),
                'component' => trim($request->post('component', '')),
                'sort_min' => $request->post('sort_min'),
                'sort_max' => $request->post('sort_max'),
                'created_start' => $request->post('created_start'),
                'created_end' => $request->post('created_end'),
                'maintain_hierarchy' => $request->post('maintain_hierarchy', 'true') === 'true'
            ];

            // 执行高级搜索
            $results = $this->searchService->advancedSearch($searchParams);

            // 格式化结果
            $formattedResults = [];
            foreach ($results as $menu) {
                $formattedResults[] = $this->transformService->formatForApi($menu);
            }

            return $this->success([
                'results' => $formattedResults,
                'search_params' => $searchParams,
                'total' => count($formattedResults)
            ], '高级搜索成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '高级搜索失败：' . $e->getMessage());
        }
    }

    /**
     * 过滤菜单
     * GET /api/menus/filter
     * @param Request $request
     * @return Response
     */
    public function filter(Request $request): Response
    {
        try {
            // 过滤条件
            $filters = [
                'status' => $request->get('status') !== null ? (bool)$request->get('status') : null,
                'hidden' => $request->get('hidden') !== null ? (bool)$request->get('hidden') : null,
                'menu_types' => $request->get('menu_types', []),
                'parent_id' => $request->get('parent_id') !== null ? (int)$request->get('parent_id') : null,
                'has_children' => $request->get('has_children') !== null ? (bool)$request->get('has_children') : null,
                'has_permission' => $request->get('has_permission') !== null ? (bool)$request->get('has_permission') : null,
                'is_external' => $request->get('is_external') !== null ? (bool)$request->get('is_external') : null,
                'maintain_hierarchy' => $request->get('maintain_hierarchy', 'true') === 'true'
            ];

            // 执行过滤
            $results = $this->searchService->filterMenus($filters);

            // 格式化结果
            $formattedResults = [];
            foreach ($results as $menu) {
                $formattedResults[] = $this->transformService->formatForApi($menu);
            }

            return $this->success([
                'results' => $formattedResults,
                'filters' => $filters,
                'total' => count($formattedResults)
            ], '菜单过滤成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '菜单过滤失败：' . $e->getMessage());
        }
    }

    /**
     * 递归搜索菜单
     * GET /api/menus/recursive-search
     * @param Request $request
     * @return Response
     */
    public function recursiveSearch(Request $request): Response
    {
        try {
            $keyword = trim($request->get('keyword', ''));
            $parentId = (int)$request->get('parent_id', 0);
            
            // 搜索选项
            $options = [
                'search_fields' => $request->get('search_fields', ['name', 'title', 'path']),
                'include_disabled' => $request->get('include_disabled', 'false') === 'true',
                'include_hidden' => $request->get('include_hidden', 'false') === 'true',
                'menu_types' => $request->get('menu_types', [])
            ];

            // 执行递归搜索
            $results = $this->searchService->recursiveSearch($keyword, $parentId, $options);

            // 格式化结果
            $formattedResults = [];
            foreach ($results as $menu) {
                $formattedResults[] = $this->transformService->formatForApi($menu);
            }

            return $this->success([
                'results' => $formattedResults,
                'keyword' => $keyword,
                'parent_id' => $parentId,
                'options' => $options,
                'total' => count($formattedResults)
            ], '递归搜索成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '递归搜索失败：' . $e->getMessage());
        }
    }

    /**
     * 搜索菜单并返回统计信息
     * GET /api/menus/search-with-stats
     * @param Request $request
     * @return Response
     */
    public function searchWithStats(Request $request): Response
    {
        try {
            $keyword = trim($request->get('keyword', ''));
            
            // 搜索选项
            $options = [
                'search_fields' => $request->get('search_fields', ['name', 'title', 'path']),
                'include_disabled' => $request->get('include_disabled', 'false') === 'true',
                'include_hidden' => $request->get('include_hidden', 'false') === 'true',
                'menu_types' => $request->get('menu_types', []),
                'maintain_hierarchy' => $request->get('maintain_hierarchy', 'true') === 'true',
                'parent_id' => $request->get('parent_id') !== null ? (int)$request->get('parent_id') : null
            ];

            // 执行搜索并获取统计信息
            $result = $this->searchService->searchWithStats($keyword, $options);

            // 格式化结果
            $formattedResults = [];
            foreach ($result['results'] as $menu) {
                $formattedResults[] = $this->transformService->formatForApi($menu);
            }

            return $this->success([
                'results' => $formattedResults,
                'stats' => $result['stats'],
                'keyword' => $result['keyword'],
                'options' => $result['options']
            ], '搜索统计成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '搜索统计失败：' . $e->getMessage());
        }
    }

    /**
     * 获取搜索建议
     * GET /api/menus/search-suggestions
     * @param Request $request
     * @return Response
     */
    public function searchSuggestions(Request $request): Response
    {
        try {
            $keyword = trim($request->get('keyword', ''));
            $limit = (int)$request->get('limit', 10);

            // 获取搜索建议
            $suggestions = $this->searchService->getSearchSuggestions($keyword, $limit);

            return $this->success([
                'suggestions' => $suggestions,
                'keyword' => $keyword,
                'limit' => $limit
            ], '获取搜索建议成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取搜索建议失败：' . $e->getMessage());
        }
    }

    // ==================== 菜单排序和状态管理接口 ====================

    /**
     * 更新菜单排序
     * PUT /api/menus/{id}/sort
     * @param Request $request
     * @return Response
     */
    public function updateSort(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            $sort = (int)$request->post('sort', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }
            
            if ($sort < 0) {
                return $this->error(Code::PARAMETER_ERROR, '排序值必须为非负整数');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 更新排序
            $result = $this->menuModel->updateSortById($id, $sort);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '更新排序失败');
            }

            return $this->success(null, '更新排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '更新排序失败：' . $e->getMessage());
        }
    }

    /**
     * 交换两个菜单的排序
     * POST /api/menus/swap-sort
     * @param Request $request
     * @return Response
     */
    public function swapSort(Request $request): Response
    {
        try {
            $id1 = (int)$request->post('id1', 0);
            $id2 = (int)$request->post('id2', 0);
            
            if ($id1 <= 0 || $id2 <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }
            
            if ($id1 == $id2) {
                return $this->error(Code::PARAMETER_ERROR, '不能交换相同的菜单');
            }

            // 检查菜单是否存在
            $menu1 = $this->menuModel->find($id1);
            $menu2 = $this->menuModel->find($id2);
            
            if (!$menu1 || !$menu2) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 交换排序
            $result = $this->menuModel->swapSort($id1, $id2);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '交换排序失败');
            }

            return $this->success(null, '交换排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '交换排序失败：' . $e->getMessage());
        }
    }

    /**
     * 移动菜单到指定位置
     * POST /api/menus/{id}/move
     * @param Request $request
     * @return Response
     */
    public function moveMenu(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            $targetId = (int)$request->post('target_id', 0);
            $parentId = $request->post('parent_id') !== null ? (int)$request->post('parent_id') : null;
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 检查是否可以移动
            if ($parentId !== null) {
                $canMove = $this->menuModel->canMoveTo($id, $parentId);
                if (!$canMove['can_move']) {
                    return $this->error(Code::PARAMETER_ERROR, $canMove['reason']);
                }
            }

            // 移动菜单
            $result = $this->menuModel->moveMenu($id, $targetId, $parentId);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '移动菜单失败');
            }

            return $this->success(null, '移动菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '移动菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 重新排序同级菜单
     * POST /api/menus/reorder-siblings
     * @param Request $request
     * @return Response
     */
    public function reorderSiblings(Request $request): Response
    {
        try {
            $parentId = (int)$request->post('parent_id', 0);
            $menuIds = $request->post('menu_ids', []);
            
            if (!is_array($menuIds) || empty($menuIds)) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID列表不能为空');
            }

            // 验证菜单ID格式
            foreach ($menuIds as $menuId) {
                if (!is_numeric($menuId) || $menuId <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }
            }

            // 重新排序
            $result = $this->menuModel->reorderSiblings($parentId, $menuIds);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '重新排序失败');
            }

            return $this->success(null, '重新排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '重新排序失败：' . $e->getMessage());
        }
    }

    /**
     * 处理拖拽排序
     * POST /api/menus/drag-sort
     * @param Request $request
     * @return Response
     */
    public function dragSort(Request $request): Response
    {
        try {
            $dragData = $request->post('drag_data', []);
            
            if (!is_array($dragData) || empty($dragData)) {
                return $this->error(Code::PARAMETER_ERROR, '拖拽数据不能为空');
            }

            // 验证拖拽数据格式
            foreach ($dragData as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    return $this->error(Code::PARAMETER_ERROR, '拖拽数据格式错误');
                }

                if (!is_numeric($item['id']) || $item['id'] <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }

                if (isset($item['parent_id']) && (!is_numeric($item['parent_id']) || $item['parent_id'] < 0)) {
                    return $this->error(Code::PARAMETER_ERROR, '父菜单ID必须为非负整数');
                }

                if (isset($item['index']) && (!is_numeric($item['index']) || $item['index'] < 0)) {
                    return $this->error(Code::PARAMETER_ERROR, '索引必须为非负整数');
                }
            }

            // 处理拖拽排序
            $result = $this->menuModel->handleDragSort($dragData);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '拖拽排序失败');
            }

            return $this->success(null, '拖拽排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '拖拽排序失败：' . $e->getMessage());
        }
    }

    /**
     * 启用菜单
     * PUT /api/menus/{id}/enable
     * @param Request $request
     * @return Response
     */
    public function enable(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 启用菜单
            $result = $this->menuModel->enableMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '启用菜单失败');
            }

            return $this->success(null, '启用菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '启用菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 禁用菜单
     * PUT /api/menus/{id}/disable
     * @param Request $request
     * @return Response
     */
    public function disable(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 禁用菜单
            $result = $this->menuModel->disableMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '禁用菜单失败');
            }

            return $this->success(null, '禁用菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '禁用菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 切换菜单状态
     * PUT /api/menus/{id}/toggle-status
     * @param Request $request
     * @return Response
     */
    public function toggleStatus(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 切换状态
            $result = $this->menuModel->toggleStatus($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '切换状态失败');
            }

            // 获取新状态
            $updatedMenu = $this->menuModel->find($id);
            $newStatus = $updatedMenu->status ? '启用' : '禁用';

            return $this->success(['status' => $updatedMenu->status], "菜单已{$newStatus}");

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '切换状态失败：' . $e->getMessage());
        }
    }

    /**
     * 批量启用菜单
     * PUT /api/menus/batch-enable
     * @param Request $request
     * @return Response
     */
    public function batchEnable(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID列表不能为空');
            }

            // 验证ID格式
            foreach ($ids as $id) {
                if (!is_numeric($id) || $id <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }
            }

            // 批量启用
            $result = $this->menuModel->batchEnable($ids);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '批量启用失败');
            }

            return $this->success(null, '批量启用成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '批量启用失败：' . $e->getMessage());
        }
    }

    /**
     * 批量禁用菜单
     * PUT /api/menus/batch-disable
     * @param Request $request
     * @return Response
     */
    public function batchDisable(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID列表不能为空');
            }

            // 验证ID格式
            foreach ($ids as $id) {
                if (!is_numeric($id) || $id <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }
            }

            // 批量禁用
            $result = $this->menuModel->batchDisable($ids);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '批量禁用失败');
            }

            return $this->success(null, '批量禁用成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '批量禁用失败：' . $e->getMessage());
        }
    }

    /**
     * 显示菜单（取消隐藏）
     * PUT /api/menus/{id}/show
     * @param Request $request
     * @return Response
     */
    public function show_menu(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 显示菜单
            $result = $this->menuModel->showMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '显示菜单失败');
            }

            return $this->success(null, '显示菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '显示菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 隐藏菜单
     * PUT /api/menus/{id}/hide
     * @param Request $request
     * @return Response
     */
    public function hideMenu(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 隐藏菜单
            $result = $this->menuModel->hideMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '隐藏菜单失败');
            }

            return $this->success(null, '隐藏菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '隐藏菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 切换菜单显示状态
     * PUT /api/menus/{id}/toggle-visibility
     * @param Request $request
     * @return Response
     */
    public function toggleVisibility(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 切换显示状态
            $result = $this->menuModel->toggleVisibility($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '切换显示状态失败');
            }

            // 获取新状态
            $updatedMenu = $this->menuModel->find($id);
            $newVisibility = $updatedMenu->hidden ? '隐藏' : '显示';

            return $this->success(['hidden' => $updatedMenu->hidden], "菜单已{$newVisibility}");

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '切换显示状态失败：' . $e->getMessage());
        }
    }

    /**
     * 软删除菜单
     * DELETE /api/menus/{id}/soft
     * @param Request $request
     * @return Response
     */
    public function softDelete(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(Code::MENU_NOT_FOUND, '菜单不存在');
            }

            // 软删除菜单
            $result = $this->menuModel->softDeleteMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '删除菜单失败');
            }

            return $this->success(null, '删除菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '删除菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 恢复已删除的菜单
     * PUT /api/menus/{id}/restore
     * @param Request $request
     * @return Response
     */
    public function restore(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 恢复菜单
            $result = $this->menuModel->restoreMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '恢复菜单失败');
            }

            return $this->success(null, '恢复菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '恢复菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 批量软删除菜单
     * DELETE /api/menus/batch-soft-delete
     * @param Request $request
     * @return Response
     */
    public function batchSoftDelete(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID列表不能为空');
            }

            // 验证ID格式
            foreach ($ids as $id) {
                if (!is_numeric($id) || $id <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }
            }

            // 批量软删除
            $result = $this->menuModel->batchSoftDelete($ids);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '批量删除失败');
            }

            return $this->success(null, '批量删除成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '批量删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量恢复菜单
     * PUT /api/menus/batch-restore
     * @param Request $request
     * @return Response
     */
    public function batchRestore(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID列表不能为空');
            }

            // 验证ID格式
            foreach ($ids as $id) {
                if (!is_numeric($id) || $id <= 0) {
                    return $this->error(Code::PARAMETER_ERROR, '菜单ID必须为正整数');
                }
            }

            // 批量恢复
            $result = $this->menuModel->batchRestore($ids);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '批量恢复失败');
            }

            return $this->success(null, '批量恢复成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '批量恢复失败：' . $e->getMessage());
        }
    }

    /**
     * 永久删除菜单
     * DELETE /api/menus/{id}/force
     * @param Request $request
     * @return Response
     */
    public function forceDelete(Request $request): Response
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return $this->error(Code::PARAMETER_ERROR, '菜单ID无效');
            }

            // 永久删除菜单
            $result = $this->menuModel->forceDeleteMenu($id);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '永久删除菜单失败');
            }

            return $this->success(null, '永久删除菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '永久删除菜单失败：' . $e->getMessage());
        }
    }

    /**
     * 获取已删除的菜单列表
     * GET /api/menus/deleted
     * @param Request $request
     * @return Response
     */
    public function deletedMenus(Request $request): Response
    {
        try {
            $page = (int)$request->get('page', 1);
            $limit = (int)$request->get('limit', 15);

            // 获取已删除的菜单
            $paginator = $this->menuModel->getDeletedMenus($page, $limit);
            
            // 格式化数据
            $list = [];
            foreach ($paginator->items() as $menu) {
                $list[] = $this->transformService->formatForApi($menu->toArray());
            }

            $result = [
                'list' => $list,
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'limit' => $paginator->listRows(),
                'pages' => $paginator->lastPage()
            ];

            return $this->success($result, '获取已删除菜单列表成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取已删除菜单列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单排序统计信息
     * GET /api/menus/sort-stats
     * @param Request $request
     * @return Response
     */
    public function sortStats(Request $request): Response
    {
        try {
            $parentId = (int)$request->get('parent_id', 0);

            // 获取排序统计信息
            $stats = $this->menuModel->getSortStats($parentId);

            return $this->success($stats, '获取排序统计信息成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取排序统计信息失败：' . $e->getMessage());
        }
    }

    /**
     * 修复菜单排序
     * POST /api/menus/fix-sort
     * @param Request $request
     * @return Response
     */
    public function fixSort(Request $request): Response
    {
        try {
            $parentId = (int)$request->post('parent_id', 0);

            // 修复排序
            $result = $this->menuModel->fixSort($parentId);
            if (!$result) {
                return $this->error(Code::SYSTEM_ERROR, '修复排序失败');
            }

            return $this->success(null, '修复排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '修复排序失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单状态统计
     * GET /api/menus/status-stats
     * @param Request $request
     * @return Response
     */
    public function statusStats(Request $request): Response
    {
        try {
            // 获取状态统计信息
            $stats = $this->menuModel->getStatusStats();

            return $this->success($stats, '获取状态统计信息成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(Code::SYSTEM_ERROR, '获取状态统计信息失败：' . $e->getMessage());
        }
    }

    /**
     * 根据错误码获取HTTP状态码
     * @param int $errorCode
     * @return int
     */
    private function getHttpCodeByErrorCode(int $errorCode): int
    {
        switch ($errorCode) {
            case Code::PARAMETER_ERROR:
                return 400;
            case Code::MENU_NOT_FOUND:
                return 404;
            case Code::SYSTEM_ERROR:
            default:
                return 500;
        }
    }
}