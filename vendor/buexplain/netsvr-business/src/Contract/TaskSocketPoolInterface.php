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

interface TaskSocketPoolInterface
{
    public function loopHeartbeat(): void;
    /**
     * 从连接池得到一个连接
     * @return TaskSocketInterface
     */
    public function get(): TaskSocketInterface;

    /**
     * 将连接归还给连接池
     * @param TaskSocketInterface $socket
     * @return void
     */
    public function release(TaskSocketInterface $socket): void;
}