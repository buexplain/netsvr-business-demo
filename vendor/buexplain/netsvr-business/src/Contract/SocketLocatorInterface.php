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

namespace NetsvrBusiness\Contract;

interface SocketLocatorInterface
{
    /**
     * 将客户的uniqId转换为所在网关的serverId
     * @param string $uniqId
     * @return int
     */
    public function convertUniqIdToServerId(string $uniqId):int;

    /**
     * 获取一个同步的socket
     * @param int $serverId
     * @return SocketInterface|null
     */
    public function getTaskSocketByServerId(int $serverId): ?SocketInterface;

    /**
     * 获取一个异步的socket，只能用于：业务进程单向请求网关的指令
     * @param int $serverId
     * @return SocketInterface|null
     */
    public function getMainSocketByServerId(int $serverId): ?SocketInterface;


    /**
     * @param string $uniqId
     * @return SocketInterface|null
     */
    public function getTaskSocketByUniqId(string $uniqId): ?SocketInterface;

    /**
     * 获取一个异步的socket，只能用于：业务进程单向请求网关的指令
     * @param string $uniqId
     * @return SocketInterface|null
     */
    public function getMainSocketByUniqId(string $uniqId): ?SocketInterface;

    /**
     * 获取所有同步的socket
     * @return array|SocketInterface[]
     */
    public function getTaskSockets(): array;

    /**
     * 获取所有异步的socket，只能用于：业务进程单向请求网关的指令
     * @return array|SocketInterface[]
     */
    public function getMainSockets(): array;
}