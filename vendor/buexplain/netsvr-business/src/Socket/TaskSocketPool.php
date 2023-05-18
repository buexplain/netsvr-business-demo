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

namespace NetsvrBusiness\Socket;

use Netsvr\Constant;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use Swoole\Coroutine\Channel;
use Throwable;
use RuntimeException;

class TaskSocketPool implements TaskSocketPoolInterface
{
    protected Channel $pool;
    protected int $num = 0;
    protected array $config = [];

    public function __construct(array $config)
    {
        $this->pool = new Channel($config['taskSocketPoolMaxConnections']);
        $this->config = $config;
        $this->config['pool'] = $this;
    }

    public function loopHeartbeat(): void
    {
        $ret = [];
        for ($i = 0; $i < $this->pool->capacity; $i++) {
            $socket = $this->pool->pop(0.01);
            if (!$socket instanceof TaskSocketInterface) {
                continue;
            }
            try {
                $id = spl_object_id($socket);
                if (!isset($ret[$id])) {
                    $ret[$id] = true;
                    $socket->send(Constant::PING_MESSAGE);
                    $socket->receive();
                }
            } catch (Throwable) {
            } finally {
                $socket->release();
            }
        }
    }

    /**
     * @return TaskSocketInterface
     * @throws Throwable
     */
    public function get(): TaskSocketInterface
    {
        if ($this->pool->isEmpty() && $this->num < $this->pool->capacity) {
            $connection = \Hyperf\Support\make(TaskSocketInterface::class, $this->config);
            $this->num++;
            return $connection;
        }
        $connection = $this->pool->pop($this->config['taskSocketPoolWaitTimeout']);
        if (!$connection instanceof TaskSocketInterface) {
            throw new RuntimeException('TaskSocketPool pool exhausted. Cannot establish new connection before wait_timeout.');
        }
        return $connection;
    }

    public function release(TaskSocketInterface $socket): void
    {
        $this->pool->push($socket);
    }
}