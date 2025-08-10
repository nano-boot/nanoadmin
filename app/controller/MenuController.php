<?php

namespace plugin\theadmin\app\controller;

use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\service\MenuService;

/**
 * 菜单控制器
 */
class MenuController
{
    private MenuService $menuService;

    public function __construct()
    {
        $this->menuService = new MenuService();
    }

    /**
     * 获取菜单树
     * GET /sys/menus
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $params = [
                'keyword' => $request->get('keyword', ''),
                'status' => $request->get('status', ''),
                'type' => $request->get('type', ''),
            ];

            $result = $this->menuService->getMenuTree($params);

            $response = ApiResponse::success($result, '获取菜单树成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取菜单树失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取菜单详情
     * GET /sys/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $menu = $this->menuService->getMenuById($id);

            $response = ApiResponse::success($menu, '获取菜单详情成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取菜单详情失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 创建菜单
     * POST /sys/menus
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            $data = [
                'parent_id' => (int)$request->post('parent_id', 0),
                'name' => $request->post('name', ''),
                'title' => $request->post('title', ''),
                'icon' => $request->post('icon', ''),
                'path' => $request->post('path', ''),
                'component' => $request->post('component', ''),
                'redirect' => $request->post('redirect', ''),
                'type' => $request->post('type', 'menu'),
                'permission' => $request->post('permission', ''),
                'sort' => (int)$request->post('sort', 0),
                'status' => (int)$request->post('status', 1),
                'is_hidden' => (int)$request->post('is_hidden', 0),
                'is_cache' => (int)$request->post('is_cache', 1),
                'is_affix' => (int)$request->post('is_affix', 0),
            ];

            // 参数验证
            $validation = $this->validateMenuData($data, true);
            if ($validation !== true) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $menu = $this->menuService->createMenu($data);

            $response = ApiResponse::created($menu, '创建菜单成功');
            return new Response(201, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '创建菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 更新菜单
     * PUT /sys/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $data = [
                'parent_id' => (int)$request->post('parent_id', 0),
                'name' => $request->post('name', ''),
                'title' => $request->post('title', ''),
                'icon' => $request->post('icon', ''),
                'path' => $request->post('path', ''),
                'component' => $request->post('component', ''),
                'redirect' => $request->post('redirect', ''),
                'type' => $request->post('type', 'menu'),
                'permission' => $request->post('permission', ''),
                'sort' => (int)$request->post('sort', 0),
                'status' => (int)$request->post('status', 1),
                'is_hidden' => (int)$request->post('is_hidden', 0),
                'is_cache' => (int)$request->post('is_cache', 1),
                'is_affix' => (int)$request->post('is_affix', 0),
            ];

            // 参数验证
            $validation = $this->validateMenuData($data, false);
            if ($validation !== true) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $menu = $this->menuService->updateMenu($id, $data);

            $response = ApiResponse::updated($menu, '更新菜单成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '更新菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 删除菜单
     * DELETE /sys/menus/{id}
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '菜单ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $this->menuService->deleteMenu($id);

            $response = ApiResponse::deleted('删除菜单成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '删除菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 菜单排序
     * POST /sys/menus/sort
     * @param Request $request
     * @return Response
     */
    public function sort(Request $request)
    {
        try {
            $sortData = $request->post('sort_data', []);
            
            if (!is_array($sortData) || empty($sortData)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '排序数据格式错误');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 验证排序数据格式
            foreach ($sortData as $item) {
                if (!is_array($item) || !isset($item['id']) || !isset($item['sort'])) {
                    $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '排序数据格式错误');
                    return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
                }

                if (!is_numeric($item['id']) || !is_numeric($item['sort'])) {
                    $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '排序数据必须为数字');
                    return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
                }
            }

            $result = $this->menuService->sortMenus($sortData);

            $response = ApiResponse::success($result, '菜单排序成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '菜单排序失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 批量删除菜单
     * DELETE /sys/menus/batch
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request)
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '请选择要删除的菜单');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '菜单ID列表无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $result = $this->menuService->batchDeleteMenus($ids);

            $response = ApiResponse::success($result, '批量删除菜单成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '批量删除菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 验证菜单数据
     * @param array $data
     * @param bool $isCreate
     * @return string|true
     */
    private function validateMenuData(array $data, bool $isCreate = false)
    {
        // 菜单名称验证
        if (empty($data['name'])) {
            return '菜单名称不能为空';
        }

        if (strlen($data['name']) > 50) {
            return '菜单名称长度不能超过50个字符';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['name'])) {
            return '菜单名称只能包含字母、数字、下划线和连字符';
        }

        // 菜单标题验证
        if (empty($data['title'])) {
            return '菜单标题不能为空';
        }

        if (strlen($data['title']) > 100) {
            return '菜单标题长度不能超过100个字符';
        }

        // 菜单类型验证
        $allowedTypes = ['menu', 'button', 'link'];
        if (!in_array($data['type'], $allowedTypes)) {
            return '菜单类型无效';
        }

        // 路径验证（菜单类型需要路径）
        if ($data['type'] === 'menu' && empty($data['path'])) {
            return '菜单类型必须设置路径';
        }

        if (!empty($data['path']) && strlen($data['path']) > 200) {
            return '菜单路径长度不能超过200个字符';
        }

        // 组件验证
        if (!empty($data['component']) && strlen($data['component']) > 200) {
            return '组件路径长度不能超过200个字符';
        }

        // 重定向验证
        if (!empty($data['redirect']) && strlen($data['redirect']) > 200) {
            return '重定向路径长度不能超过200个字符';
        }

        // 权限验证
        if (!empty($data['permission']) && strlen($data['permission']) > 100) {
            return '权限标识长度不能超过100个字符';
        }

        // 图标验证
        if (!empty($data['icon']) && strlen($data['icon']) > 100) {
            return '图标名称长度不能超过100个字符';
        }

        // 排序值验证
        if ($data['sort'] < 0 || $data['sort'] > 9999) {
            return '排序值必须在0-9999之间';
        }

        // 状态验证
        if (!in_array($data['status'], [0, 1])) {
            return '状态值无效';
        }

        // 布尔值验证
        if (!in_array($data['is_hidden'], [0, 1])) {
            return '隐藏状态值无效';
        }

        if (!in_array($data['is_cache'], [0, 1])) {
            return '缓存状态值无效';
        }

        if (!in_array($data['is_affix'], [0, 1])) {
            return '固定状态值无效';
        }

        return true;
    }
}