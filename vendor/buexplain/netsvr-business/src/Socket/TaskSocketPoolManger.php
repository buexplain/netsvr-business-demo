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
use Hyperf\Contract\StdoutLoggerInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use NetsvrBusiness\Exception\DuplicateServerIdException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

class TaskSocketPoolManger implements TaskSocketPoolMangerInterface
{
    /**
     * @var StdoutLoggerInterface
     */
    protected StdoutLoggerInterface $logger;

    /**
     * @var array|TaskSocketPoolInterface[]
     */
    protected array $pools = [];

    protected Channel $heartbeatIntervalCh;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $this->heartbeatIntervalCh = new Channel();
        $config = (array)\Hyperf\Config\config('business.netsvrWorkers', []);
        if (empty($config)) {
            throw new RuntimeException('The business service config business.php not found, may be not run command： php bin/hyperf.php vendor:publish buexplain/netsvr-business', 1);
        }
        foreach ($config as $item) {
            $pool = \Hyperf\Support\make(TaskSocketPoolInterface::class, ['config' => $item]);
            if (isset($this->pools[$item['serverId']])) {
                throw new DuplicateServerIdException('serverId option in file business.php is duplicate: ' . $item['serverId']);
            }
            $this->pools[$item['serverId']] = $pool;
            $this->loopHeartbeat($pool, (float)$item['heartbeatInterval']);
        }
    }

    protected function loopHeartbeat(TaskSocketPoolInterface $pool, float $heartbeatInterval)
    {
        Coroutine::create(function () use ($pool, $heartbeatInterval) {
            while ($this->heartbeatIntervalCh->pop($heartbeatInterval) === false && $this->heartbeatIntervalCh->errCode != SWOOLE_CHANNEL_CLOSED) {
                $pool->loopHeartbeat();
            }
        });
    }

    public function close()
    {
        //关闭这个协程，否则进程因为还有协程活着，无法退出
        $this->heartbeatIntervalCh->close();
    }

    /**
     * @return array|TaskSocketInterface[]
     * @throws Throwable
     */
    public function getSockets(): array
    {
        /**
         * @var $ret TaskSocketInterface[]
         */
        $ret = [];
        foreach ($this->pools as $pool) {
            try {
                $ret[] = $pool->get();
            } catch (Throwable $throwable) {
                //有一个池子没拿成功，则将已经拿出来的归还掉，并抛出异常
                foreach ($ret as $item) {
                    $item->release();
                }
                throw $throwable;
            }
        }
        return $ret;
    }

    /**
     * @param int $serverId
     * @return TaskSocketInterface|null
     */
    public function getSocketByServerId(int $serverId): ?TaskSocketInterface
    {
        if (isset($this->pools[$serverId])) {
            return $this->pools[$serverId]->get();
        }
        return null;
    }
}