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

namespace NetsvrBusiness\Listener;

use Hyperf\Command\Event\AfterExecute;
use Hyperf\Framework\Event\OnWorkerExit;
use Hyperf\Process\Event\AfterProcessHandle;
use Hyperf\Context\ApplicationContext;
use Hyperf\Event\Contract\ListenerInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Event\ServerStop;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Hyperf\Server\Event\AllCoroutineServersClosed;

class CloseListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            //命令进程行结束
            AfterExecute::class,
            //swoole异步风格服务器进程结束
            OnWorkerExit::class,
            //自定义进程结束
            AfterProcessHandle::class,
            //协程风格服务器进程结束
            AllCoroutineServersClosed::class,
            //本business服务器进程结束
            ServerStop::class,
        ];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(object $event): void
    {
        if (ApplicationContext::getContainer()->has(TaskSocketPoolMangerInterface::class)) {
            ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->close();
        }
    }
}
