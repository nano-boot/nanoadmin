<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\file\FileBatchDeleteRequest;
use plugin\nanoadmin\app\schema\file\FileQuery;
use plugin\nanoadmin\app\schema\file\FileRequest;
use plugin\nanoadmin\app\schema\file\FileResponse;
use plugin\nanoadmin\app\schema\file\FileStatsResponse;
use plugin\nanoadmin\app\schema\file\FileUploadResponse;
use plugin\nanoadmin\app\service\FileService;
use plugin\nanoadmin\app\validator\file\FileValidator;
use support\Request;
use support\Response;
use support\annotation\Middleware;

/**
 * 文件控制器
 *
 * Phase 2 注解化（来源：authorization-refactoring-plan.md §1）：
 *  - 类级 #[Permission] 提供兜底权限码 sys:file
 *  - 方法级 #[Permission] 精确声明每个方法的权限码（与 route_permissions 对齐）
 *  - 本 controller 使用 sys:file:list 作为查询权限码（区别于其他 controller 的 :page），
 *    迁移时保持兼容，§2.6 重命名（:list → :query）属于 M2.5 工作。
 */
#[OA\Tag(name: '文件管理', description: '文件上传、下载、管理')]
#[Permission(title: '文件管理', code: 'sys:file', module: 'system')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class FileController extends BaseController
{
    private FileService $fileService;
    private FileValidator $validator;

    public function __construct(FileService $fileService, FileValidator $validator)
    {
        $this->fileService = $fileService;
        $this->validator = $validator;
    }

    protected function getService(): FileService
    {
        return $this->fileService;
    }

    protected function getModelName(): string
    {
        return 'File';
    }

    /**
     * 获取文件列表
     */
    #[OA\Get(
        path: '/sys/files',
        summary: '文件列表',
        description: '获取文件分页列表，支持按关键词、文件类型、存储类型筛选',
        tags: ['文件管理'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => FileQuery::class]
    )]
    #[Permission(title: '文件列表', code: 'sys:file:list', module: 'system', action: 'page')]
    #[PageResponse(schema: FileResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('list')->setGet()->check();
        return R::paginate($this->fileService->getPage($params));
    }

    /**
     * 获取文件详情
     */
    #[OA\Get(
        path: '/sys/files/{id}',
        summary: '文件详情',
        description: '根据ID获取文件详细信息',
        tags: ['文件管理'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '文件ID'],
        ]]
    )]
    #[Permission(title: '文件详情', code: 'sys:file:list', module: 'system', action: 'page')]
    #[DataResponse(schema: FileResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->fileService->getById($params['id']), '获取文件详情成功');
    }

    /**
     * 上传单个文件
     */
    #[OA\Post(
        path: '/sys/files/upload',
        summary: '上传文件',
        description: '上传单个文件，支持本地存储和云存储',
        tags: ['文件管理']
    )]
    #[Permission(title: '上传文件', code: 'sys:file:create', module: 'system', action: 'create')]
    #[DataResponse(schema: FileUploadResponse::class)]
    public function upload(Request $request): Response
    {
        $uploadedFile = $request->file('file');
        if (!$uploadedFile) {
            throw new ApiException('请选择要上传的文件', Code::PARAMETER_ERROR->value);
        }

        $params = [
            'storage_type' => $request->post('storage_type', 'local'),
            'bucket_name' => $request->post('bucket_name', ''),
            'created_by' => $request->post('created_by', 0),
            'file_type' => $request->post('file_type', null),
        ];

        $file = $this->fileService->uploadFile($uploadedFile, $params);
        return R::data($file, '文件上传成功');
    }

    /**
     * 批量上传文件
     */
    #[OA\Post(
        path: '/sys/files/batch',
        summary: '批量上传文件',
        description: '批量上传多个文件',
        tags: ['文件管理']
    )]
    #[Permission(title: '批量上传文件', code: 'sys:file:create', module: 'system', action: 'create')]
    #[DataResponse(schema: FileUploadResponse::class)]
    public function batchUpload(Request $request): Response
    {
        $uploadedFiles = $request->file('files');
        if (!$uploadedFiles || !is_array($uploadedFiles)) {
            throw new ApiException('请选择要上传的文件', Code::PARAMETER_ERROR->value);
        }

        $params = [
            'storage_type' => $request->post('storage_type', 'local'),
            'bucket_name' => $request->post('bucket_name', ''),
            'created_by' => $request->post('created_by', 0),
        ];

        $results = $this->fileService->batchUploadFiles($uploadedFiles, $params);
        return R::data($results, '批量上传完成');
    }

    /**
     * 更新文件信息
     */
    #[OA\Put(
        path: '/sys/files/{id}',
        summary: '更新文件信息',
        description: '更新文件的名称、路径、状态等信息',
        tags: ['文件管理'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '文件ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => FileRequest::class
        ]
    )]
    #[Permission(title: '更新文件信息', code: 'sys:file:update', module: 'system', action: 'update')]
    #[DataResponse(schema: FileResponse::class)]
    public function update(Request $request, int $id): Response
    {
        $data = $this->validator->scene('update')->setAll()->check();
        $data['updated_by'] = $request->post('updated_by', 0);
        $file = $this->fileService->update($id, $data);
        return R::data($file, '更新文件信息成功');
    }

    /**
     * 删除文件
     */
    #[OA\Delete(
        path: '/sys/files/{id}',
        summary: '删除文件',
        description: '删除指定ID的文件，包括物理文件和数据库记录',
        tags: ['文件管理'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '文件ID'],
        ]]
    )]
    #[Permission(title: '删除文件', code: 'sys:file:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $params = $this->validator->scene('destroy')->setPath()->check();
        $this->fileService->deleteFile($params['id']);
        return R::ok('删除文件成功');
    }

    /**
     * 批量删除文件
     */
    #[OA\Delete(
        path: '/sys/files/batch',
        summary: '批量删除文件',
        description: '批量删除多个文件，包括物理文件和数据库记录',
        tags: ['文件管理'],
        x: [OpenApiModifier::X_REQUEST_BODY => FileBatchDeleteRequest::class]
    )]
    #[Permission(title: '批量删除文件', code: 'sys:file:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $data = $this->validator->scene('batchDestroy')->setPost()->check();
        $result = $this->fileService->batchDeleteFiles($data['ids']);
        return R::success($result, '批量删除文件成功');
    }

    /**
     * 下载文件
     */
    #[OA\Get(
        path: '/sys/files/{id}/download',
        summary: '下载文件',
        description: '获取文件下载链接或直接下载文件',
        tags: ['文件管理'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '文件ID'],
        ]]
    )]
    #[Permission(title: '下载文件', code: 'sys:file:list', module: 'system', action: 'page')]
    #[DataResponse()]
    public function download(Request $request, int $id): Response
    {
        $params = $this->validator->scene('download')->setPath()->check();
        $downloadInfo = $this->fileService->downloadFile($params['id']);

        if ($downloadInfo['storage_type'] === 'local') {
            $filePath = public_path('uploads/' . $downloadInfo['file_path']);
            if (!file_exists($filePath)) {
                throw new ApiException('文件不存在', Code::PARAMETER_ERROR->value);
            }
            return response()->download($filePath, $downloadInfo['file_name']);
        }

        return R::data(['url' => $downloadInfo['file_url']], '获取下载链接成功');
    }

    /**
     * 获取文件统计信息
     */
    #[OA\Get(
        path: '/sys/files/stats',
        summary: '文件统计',
        description: '获取文件数量、存储大小等统计信息',
        tags: ['文件管理']
    )]
    #[Permission(title: '文件统计', code: 'sys:file:list', module: 'system', action: 'page')]
    #[DataResponse(schema: FileStatsResponse::class)]
    public function stats(Request $request): Response
    {
        $stats = $this->fileService->getFileStats();
        return R::success($stats, '获取文件统计信息成功');
    }
}
