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

namespace App\Middleware;

use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Dispatcher\MiddlewareHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 记录日志的中间件
 */
class LoggerMiddleware
{
    /**
     * @param MiddlewareHandler $handler
     * @param ConnOpen|null $connOpen
     * @param ConnClose|null $connClose
     * @param Transfer|null $transfer
     * @param RouterInterface|null $clientRouter
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(MiddlewareHandler $handler, ConnOpen|null $connOpen, ConnClose|null $connClose, Transfer|null $transfer, RouterInterface|null $clientRouter): void
    {
        $handler->handle();
        if ($connOpen) {
            echo '连接打开：' . $connOpen->serializeToJsonString() . PHP_EOL;
            return;
        }
        if ($connClose) {
            echo '连接关闭：' . $connClose->serializeToJsonString() . PHP_EOL;
            return;
        }
        if ($transfer) {
            echo sprintf('收到用户消息：%s --> %d --> %s%s', $transfer->getUniqId(), $clientRouter->getCmd(), $clientRouter->getData(), PHP_EOL);
        }
    }
}
