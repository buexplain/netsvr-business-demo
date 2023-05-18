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

namespace NetsvrBusiness\Socket;

use Hyperf\Context\ApplicationContext;
use NetsvrBusiness\Contract\SocketLocatorInterface;
use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * 这个类主要是解决网关分配给客户的uniqId与网关的serverId之间的映射关系
 * 目前的实现是uniqId的前两个字符就是serverId的16进制表示，所以截取uniqId的前两个字符、转int即可得到serverId
 * 如果业务侧对网关下发给客户的uniqId进行了变更，导致上面的逻辑失效，则业务侧必须重写这个类的*ByUniqId方法，正确的处理uniqId与serverId的转换
 * 不建议业务层将客户的uniqId与网关的serverId之间的映射关系存储到redis这种需要io查询的存储器上，最好是本进程内存计算即可得到
 */
class SocketLocator implements SocketLocatorInterface
{
    /**
     * 将客户的uniqId转换为所在网关的serverId
     * @param string $uniqId
     * @return int
     */
    public function convertUniqIdToServerId(string $uniqId): int
    {
        return (int)@hexdec(substr($uniqId, 0, 2));
    }

    /**
     * @param int $serverId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getTaskSocketByServerId(int $serverId): ?SocketInterface
    {
        return ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * @param int $serverId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getMainSocketByServerId(int $serverId): ?SocketInterface
    {
        return ApplicationContext::getContainer()->get(MainSocketManagerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * @param string $uniqId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getTaskSocketByUniqId(string $uniqId): ?SocketInterface
    {
        return ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->getSocketByServerId(static::convertUniqIdToServerId($uniqId));
    }

    /**
     * @param string $uniqId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getMainSocketByUniqId(string $uniqId): ?SocketInterface
    {
        return ApplicationContext::getContainer()->get(MainSocketManagerInterface::class)->getSocketByServerId(static::convertUniqIdToServerId($uniqId));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function getTaskSockets(): array
    {
        return ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->getSockets();
    }

    /**
     * @return array|SocketInterface[]
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function getMainSockets(): array
    {
        return ApplicationContext::getContainer()->get(MainSocketManagerInterface::class)->getSockets();
    }
}