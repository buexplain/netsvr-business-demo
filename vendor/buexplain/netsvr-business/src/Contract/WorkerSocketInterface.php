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

interface WorkerSocketInterface
{
    public function connect(): void;

    public function register(): bool;

    public function unregister(): void;

    public function waitUnregisterOk(): void;

    public function close(): void;

    public function send(string $data): void;

    public function receive(): Router|false;

    public function loopHeartbeat(): void;

    public function loopSend(): void;

    public function getServerId(): int;
}