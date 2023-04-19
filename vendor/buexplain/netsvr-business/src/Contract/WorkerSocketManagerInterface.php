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

namespace NetsvrBusiness\Contract;

use Netsvr\Router;

interface WorkerSocketManagerInterface
{
    public function add(WorkerSocketInterface $socket): void;

    public function register(): bool;

    public function unregister(): void;

    public function receive(): Router|bool;

    public function close(): void;

    /**
     * 发送消息给所有网关socket
     * @param string $data
     * @return void
     */
    public function send(string $data): void;

    /**
     * 根据网关服务唯一编号，返回某个网关socket
     * @param int $serverId
     * @return WorkerSocketInterface|null
     */
    public function getSocket(int $serverId): ?WorkerSocketInterface;

    /**
     * 根据客户在网关服务中的唯一id，返回某个网关socket
     * @param string $uniqId 客户在网关服务中的唯一id，并且这个id满足条件：前两个字符是网关服务的serverId的16进制表示
     * @return WorkerSocketInterface|null
     */
    public function getSocketByPrefixUniqId(string $uniqId): ?WorkerSocketInterface;
}