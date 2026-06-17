<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\swagger\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\Operation;
use OpenApi\Generator;
use OpenApi\Processors\ProcessorInterface;
use plugin\nanoadmin\app\swagger\ApiResponseDocs;
/**
 * 给所有 operation 兜底注入 200 成功响应。
 *
 * 为什么需要这个 Processor？
 *  - swagger-php 4.x 的 Operation::$_required = ['responses']，
 *    业务方只要没声明任何 #[OA\Response] / #[DataResponse] / #[PageResponse]，
 *    Generator::generate() 走到 validate() 阶段就会抛：
 *    "@OA\\Post() requires at least one @OA\\Response()"
 *  - 我们的 modify 回调（OpenApiModifier）虽然也能补 200，但发生在 validate 之后，救不回来。
 *  - Processor 在 validate() 之前执行，所以这是正确的注入时机。
 *
 * 行为：
 *  - 仅当 operation 完全没声明任何 200 响应时，自动补一个 data: null 的标准成功响应
 *  - 已经写了 #[DataResponse] / #[PageResponse] / #[OA\Response(response: 200, ...)] 的 operation 保持原样
 *  - 不影响 401/403 公共响应（由 OpenApiModifier::injectCommonResponses 在 modify 阶段补）
 */
class InjectDefault200Response implements ProcessorInterface
{
    private Analysis $analysis;

    public function __invoke(Analysis $analysis): void
    {
        $this->analysis = $analysis;

        /** @var Operation[] $operations */
        $operations = $analysis->getAnnotationsOfType(Operation::class);

        foreach ($operations as $operation) {
            if (self::hasResponse($operation, 200)) {
                continue;
            }

            $response = new OA\Response([
                'response' => 200,
                'description' => '成功',
                'content' => [
                    'application/json' => new OA\MediaType([
                        'mediaType' => 'application/json',
                        'schema' => new OA\Schema(['ref' => ApiResponseDocs::class]),
                    ]),
                ],
            ]);

            // 200 放在最前面，便于前端阅读
            $existing = (Generator::isDefault($operation->responses) || !is_array($operation->responses))
                ? []
                : $operation->responses;
            $operation->responses = array_merge([$response], $existing);
        }
    }

    private static function hasResponse(Operation $operation, int $status): bool
    {
        if (Generator::isDefault($operation->responses) || empty($operation->responses)) {
            return false;
        }
        foreach ($operation->responses as $r) {
            if ((int) $r->response === $status) {
                return true;
            }
        }
        return false;
    }
}
