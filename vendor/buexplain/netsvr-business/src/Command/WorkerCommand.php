<?php
/**
 * Copyright 2023 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace NetsvrBusiness\Command;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;

class WorkerCommand extends Command
{
    protected string $pidFile = '';
    protected StdoutLoggerInterface $logger;

    /**
     * @param string|null $name
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->pidFile = BASE_PATH . '/runtime/business.pid';
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    protected function getPid()
    {
        clearstatcache();
        if (!file_exists($this->pidFile)) {
            return 0;
        }
        return max(intval(file_get_contents($this->pidFile)), 0);
    }

    protected function isRun(): bool
    {
        $pid = $this->getPid();
        if ($pid > 0 && Process::kill($pid, 0)) {
            return true;
        }
        @unlink($this->pidFile);
        return false;
    }
}
