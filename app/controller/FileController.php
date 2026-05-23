<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\FileService;
use plugin\theadmin\app\validator\FileValidator;

/**
 * 文件控制器
 */
class FileController extends BaseController
{
    /**
     * 文件服务实例
     * @var FileService
     */
    private FileService $fileService;

    /**
     * 构造函数 - 使用依赖注入
     * @param FileService $fileService 文件服务实例
     */
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * 获取服务实例
     * @return FileService
     */
    protected function getService(): FileService
    {
        return $this->fileService;
    }

    /**
     * 获取模型名称
     * @return string
     */
    protected function getModelName(): string
    {
        return 'File';
    }

    /**
     * 获取文件列表
     * GET /sys/files
     * @param Request $request
     * @return Response
     */
    public function page(Request $request): Response
    {
        $params = $request->get();
        $validator = new FileValidator();
        $validatedParams = $validator->validateListParams($params);
        return R::paginate($this->fileService->getPage($validatedParams));
    }

    /**
     * 获取文件详情
     * GET /sys/files/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        try {
            // 从路由参数获取ID
            $id = (int)$request->get('id', 0);
            $validator = new FileValidator();
            $validator->validateId($id);

            $file = $this->fileService->getFileById($id);
            return R::success($file, '获取文件详情成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取文件详情失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 上传单个文件
     * POST /sys/files/upload
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
    {
        try {
            // 获取上传的文件
            $uploadedFile = $request->file('file');
            if (!$uploadedFile) {
                return R::error('请选择要上传的文件', Code::PARAMETER_ERROR->value);
            }

            // 验证文件
            $validator = new FileValidator();
            $validator->validateUploadData(['file' => $uploadedFile]);

            // 获取其他参数
            $params = [
                'storage_type' => $request->post('storage_type', 'local'),
                'bucket_name' => $request->post('bucket_name', ''),
                'created_by' => $request->post('created_by', 0),
                'file_type' => $request->post('file_type', null), // 可选的文件类型参数
            ];

            $file = $this->fileService->uploadFile($uploadedFile, $params);
            return R::data($file, '文件上传成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('文件上传失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 批量上传文件
     * POST /sys/files/batch
     * @param Request $request
     * @return Response
     */
    public function batchUpload(Request $request): Response
    {
        try {
            // 获取上传的文件数组
            $uploadedFiles = $request->file('files');
            if (!$uploadedFiles || !is_array($uploadedFiles)) {
                return R::error('请选择要上传的文件', Code::PARAMETER_ERROR->value);
            }

            // 验证文件
            $validator = new FileValidator();
            $validator->validateBatchUploadData(['files' => $uploadedFiles]);

            // 获取其他参数
            $params = [
                'storage_type' => $request->post('storage_type', 'local'),
                'bucket_name' => $request->post('bucket_name', ''),
                'created_by' => $request->post('created_by', 0),
            ];

            $results = $this->fileService->batchUploadFiles($uploadedFiles, $params);
            return R::data($results, '批量上传完成');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量上传失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新文件信息
     * PUT /sys/files/{id}
     * @param Request $request
     * @param int $id
     * @param array $fields
     * @return Response
     */
    public function update(Request $request, int $id, array $fields = []): Response
    {
        try {
            $validator = new FileValidator();
            $validator->validateId($id);

            $requestData = $request->only([
                'original_name', 'file_name', 'file_path', 'status'
            ]);

            $validatedData = $validator->validateUpdateData(array_merge(['id' => $id], $requestData));

            // 添加更新者信息
            $validatedData['updated_by'] = $request->post('updated_by', 0);

            $file = $this->fileService->update($id, $validatedData);
            return R::data($file, '更新文件信息成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('更新文件信息失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 删除文件
     * DELETE /sys/files/{id}
     * @param int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            $validator = new FileValidator();
            $validator->validateId($id);

            $result = $this->fileService->deleteFile($id);
            return R::success($result, '删除文件成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('删除文件失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 批量删除文件
     * DELETE /sys/files/batch
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            $validator = new FileValidator();
            $validatedData = $validator->validateBatchIds(['ids' => $ids]);

            $result = $this->fileService->batchDeleteFiles($validatedData['ids']);
            return R::success($result, '批量删除文件成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量删除文件失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 下载文件
     * GET /sys/files/{id}/download
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function download(Request $request, int $id): Response
    {
        try {
            $validator = new FileValidator();
            $validator->validateId($id);

            $downloadInfo = $this->fileService->downloadFile($id);

            // 对于本地文件，返回文件响应
            if ($downloadInfo['storage_type'] === 'local') {
                $filePath = public_path('uploads/' . $downloadInfo['file_path']);
                if (!file_exists($filePath)) {
                    return R::error('文件不存在', Code::PARAMETER_ERROR->value);
                }

                return response()->download($filePath, $downloadInfo['file_name']);
            }

            // 对于云存储，返回重定向或预签名URL
            return R::data(['url' => $downloadInfo['file_url']], '获取下载链接成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('下载文件失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取文件统计信息
     * GET /sys/files/stats
     * @param Request $request
     * @return Response
     */
    public function stats(Request $request): Response
    {
        try {
            $stats = $this->fileService->getFileStats();
            return R::success($stats, '获取文件统计信息成功');

        } catch (\Exception $e) {
            return R::error('获取文件统计信息失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }
}
