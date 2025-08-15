<?php

namespace plugin\theadmin\app\controller;

use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\model\Menu;
use plugin\theadmin\app\service\MenuTransformService;

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
     * 构造函数
     */
    public function __construct()
    {
        $this->menuModel = new Menu();
        $this->transformService = new MenuTransformService();
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
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取菜单树形结构
     * GET /api/menus/tree
     * @param Request $request
     * @return Response
     */
    public function tree(Request $request): Response
    {
        try {
            // 获取查询参数
            $parentId = (int)$request->get('parent_id', 0);
            $onlyEnabled = $request->get('only_enabled', 'true') === 'true';
            $keyword = trim($request->get('keyword', ''));

            // 获取菜单树
            $tree = $this->menuModel->getTree($parentId, $onlyEnabled);

            // 如果有搜索关键词，进行过滤
            if (!empty($keyword)) {
                $tree = $this->filterTreeByKeyword($tree, $keyword);
            }

            // 格式化数据
            $formattedTree = $this->formatTreeForApi($tree);

            return $this->success($formattedTree, '获取菜单树成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单树失败：' . $e->getMessage());
        }
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
                return $this->error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
            }

            // 查找菜单
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(ErrorCode::MENU_NOT_FOUND, '菜单不存在');
            }

            // 转换为表单数据格式
            $formData = $this->transformService->toFormData($menu->toArray());

            return $this->success($formData, '获取菜单详情成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单详情失败：' . $e->getMessage());
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
            // 获取表单数据
            $formData = $this->getFormDataFromRequest($request);

            // 数据验证
            $validation = $this->transformService->validateMenuData($formData);
            if (!$validation['valid']) {
                return $this->error(ErrorCode::PARAMETER_ERROR, implode(', ', $validation['errors']));
            }

            // 转换为数据库格式
            $dbData = $this->transformService->fromFormData($formData);

            // 创建菜单
            $menu = $this->menuModel->createMenu($dbData);
            if (!$menu) {
                return $this->error(ErrorCode::SYSTEM_ERROR, '创建菜单失败');
            }

            // 格式化返回数据
            $result = $this->transformService->formatForApi($menu->toArray());

            return $this->success($result, '创建菜单成功', 201);

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '创建菜单失败：' . $e->getMessage());
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
                return $this->error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(ErrorCode::MENU_NOT_FOUND, '菜单不存在');
            }

            // 获取表单数据
            $formData = $this->getFormDataFromRequest($request);

            // 数据验证
            $validation = $this->transformService->validateMenuData($formData);
            if (!$validation['valid']) {
                return $this->error(ErrorCode::PARAMETER_ERROR, implode(', ', $validation['errors']));
            }

            // 转换为数据库格式
            $dbData = $this->transformService->fromFormData($formData);

            // 更新菜单
            $result = $this->menuModel->updateMenu($id, $dbData);
            if (!$result) {
                return $this->error(ErrorCode::SYSTEM_ERROR, '更新菜单失败');
            }

            // 获取更新后的菜单数据
            $updatedMenu = $this->menuModel->find($id);
            $formattedData = $this->transformService->formatForApi($updatedMenu->toArray());

            return $this->success($formattedData, '更新菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '更新菜单失败：' . $e->getMessage());
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
                return $this->error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(ErrorCode::MENU_NOT_FOUND, '菜单不存在');
            }

            // 删除菜单
            $result = $this->menuModel->deleteMenu($id);
            if (!$result) {
                return $this->error(ErrorCode::SYSTEM_ERROR, '删除菜单失败');
            }

            return $this->success(null, '删除菜单成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '删除菜单失败：' . $e->getMessage());
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
                return $this->error(ErrorCode::PARAMETER_ERROR, '排序数据格式错误');
            }

            // 验证排序数据格式
            foreach ($sortData as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    return $this->error(ErrorCode::PARAMETER_ERROR, '排序数据格式错误');
                }

                if (!is_numeric($item['id']) || $item['id'] <= 0) {
                    return $this->error(ErrorCode::PARAMETER_ERROR, '菜单ID必须为正整数');
                }

                if (isset($item['sort']) && (!is_numeric($item['sort']) || $item['sort'] < 0)) {
                    return $this->error(ErrorCode::PARAMETER_ERROR, '排序值必须为非负整数');
                }

                if (isset($item['parent_id']) && (!is_numeric($item['parent_id']) || $item['parent_id'] < 0)) {
                    return $this->error(ErrorCode::PARAMETER_ERROR, '父菜单ID必须为非负整数');
                }
            }

            // 批量更新排序
            $result = $this->menuModel->batchUpdateSort($sortData);
            if (!$result) {
                return $this->error(ErrorCode::SYSTEM_ERROR, '批量更新排序失败');
            }

            return $this->success(null, '菜单排序成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '菜单排序失败：' . $e->getMessage());
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
                return $this->error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
            }

            // 检查菜单是否存在
            $menu = $this->menuModel->find($id);
            if (!$menu) {
                return $this->error(ErrorCode::MENU_NOT_FOUND, '菜单不存在');
            }

            // 获取菜单路径
            $path = $this->menuModel->getMenuPath($id);
            
            // 构建面包屑数据
            $breadcrumbs = $this->transformService->buildBreadcrumbData($path);

            return $this->success($breadcrumbs, '获取菜单路径成功');

        } catch (ApiException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单路径失败：' . $e->getMessage());
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
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单选择器数据失败：' . $e->getMessage());
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
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单统计信息失败：' . $e->getMessage());
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
            return $this->error(ErrorCode::SYSTEM_ERROR, '获取菜单类型选项失败：' . $e->getMessage());
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
     * @param mixed $data
     * @param string $message
     * @param int $httpCode
     * @return Response
     */
    private function success($data = null, string $message = '操作成功', int $httpCode = 200): Response
    {
        $response = ApiResponse::success($data, $message);
        return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
    }

    /**
     * 错误响应
     * @param int $code
     * @param string $message
     * @return Response
     */
    private function error(int $code, string $message): Response
    {
        $response = ApiResponse::error($code, $message);
        $httpCode = $this->getHttpCodeByErrorCode($code);
        return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
    }

    /**
     * 根据错误码获取HTTP状态码
     * @param int $errorCode
     * @return int
     */
    private function getHttpCodeByErrorCode(int $errorCode): int
    {
        switch ($errorCode) {
            case ErrorCode::PARAMETER_ERROR:
                return 400;
            case ErrorCode::MENU_NOT_FOUND:
                return 404;
            case ErrorCode::SYSTEM_ERROR:
            default:
                return 500;
        }
    }
}