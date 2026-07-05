<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use plugin\nanoadmin\app\library\annotation\ReflectionCache;

/**
 * 清空 nanoadmin 反射缓存（Phase 2 新增）
 *
 * 部署脚本建议：
 *   php start.php restart
 *   php console cache:clear-reflect    # ← 新增：清空反射缓存
 *   php console migrate:run
 *
 * 来源：authorization-refactoring-plan.md §2.9.6
 */
class ClearReflectCache extends Command
{
    protected static string $defaultName = 'cache:clear-reflect';
    protected static string $defaultDescription = '清空 nanoadmin 反射缓存（代码部署后必须执行）';

    protected function configure(): void
    {
        $this->setName(self::$defaultName)
             ->setDescription('清空 nanoadmin 反射缓存（#[Permission] / #[AllowAnonymous] 的扫描结果）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (ReflectionCache::clear()) {
            $output->writeln('<info>✓ nanoadmin 反射缓存已清空</info>');
            return self::SUCCESS;
        }

        $output->writeln('<error>✗ 清空失败（缓存驱动不可用或 tag 未注册）</error>');
        return self::FAILURE;
    }
}