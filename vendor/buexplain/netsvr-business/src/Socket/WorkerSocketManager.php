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

use NetsvrBusiness\Contract\WorkerSocketInterface;
use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
use NetsvrBusiness\Exception\DuplicateServerIdException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Netsvr\Router;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

class WorkerSocketManager implements WorkerSocketManagerInterface
{
    public string $loggerPrefix = '';
    protected ?Channel $receiveCh = null;
    protected StdoutLoggerInterface $logger;
    /**
     * @var WorkerSocketInterface[]
     */
    protected array $sockets = [];

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        $this->receiveCh = new Channel();
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    public function add(WorkerSocketInterface $socket): void
    {
        if (isset($this->sockets[$socket->getServerId()])) {
            throw new DuplicateServerIdException('serverId option in file business.php is duplicate: ' . $socket->getServerId());
        }
        $this->sockets[$socket->getServerId()] = $socket;
        //这里做一到中转，将每个socket发来的数据统一转发到一个channel里面
        Coroutine::create(function () use ($socket) {
            while (true) {
                $data = $socket->receive();
                if ($data !== false) {
                    $this->receiveCh->push($data);
                    continue;
                }
                $this->logger->debug($this->loggerPrefix . 'coroutine:loopTransfer exit.');
                unset($this->sockets[$socket->getServerId()]);
                if (count($this->sockets) == 0) {
                    $this->receiveCh->close();
                }
                break;
            }
        });
    }

    /**
     * @return bool
     */
    public function register(): bool
    {
        $wg = new Coroutine\WaitGroup();
        $ok = true;
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket, &$ok) {
                try {
                    $tmp = $socket->register();
                    if ($tmp === false) {
                        $ok = false;
                    }
                } catch (Throwable $throwable) {
                    $message = sprintf(
                        "%d --> %s in %s on line %d\nThrowable: %s",
                        $throwable->getCode(),
                        $throwable->getMessage(),
                        $throwable->getFile(),
                        $throwable->getLine(),
                        get_class($throwable)
                    );
                    $this->logger->error($this->loggerPrefix . $message);
                    $ok = false;
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
        if ($ok === false) {
            return false;
        }
        foreach ($this->sockets as $socket) {
            $socket->loopSend();
            $socket->loopHeartbeat();
        }
        return true;
    }

    public function unregister(): void
    {
        $wg = new Coroutine\WaitGroup();
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket) {
                try {
                    $socket->unregister();
                    $socket->waitUnregisterOk();
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    /**
     * 读取
     * @return Router|bool
     */
    public function receive(): Router|bool
    {
        return $this->receiveCh->pop();
    }

    public function close(): void
    {
        $wg = new Coroutine\WaitGroup();
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket) {
                try {
                    $socket->close();
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    /**
     * 发送消息给所有网关socket
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        foreach ($this->sockets as $socket) {
            $socket->send($data);
        }
    }

    /**
     * 根据网关服务唯一编号，返回某个网关socket
     * @param int $serverId
     * @return WorkerSocketInterface|null
     */
    public function getSocket(int $serverId): ?WorkerSocketInterface
    {
        return $this->sockets[$serverId] ?? null;
    }

    /**
     * 根据客户的唯一id，返回某个网关socket
     * @param string $uniqId 客户在网关服务中的唯一id，并且这个id满足条件：前两个字符是网关服务的serverId的16进制表示
     * @return WorkerSocketInterface|null
     */
    public function getSocketByPrefixUniqId(string $uniqId): ?WorkerSocketInterface
    {
        $serverId = (int)hexdec(substr($uniqId, 0, 2));
        return $this->sockets[$serverId] ?? null;
    }
}