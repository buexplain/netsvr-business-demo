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

use Exception;
use Netsvr\Constant;
use Netsvr\Router;
use Swoole\Coroutine;
use Hyperf\Context\ApplicationContext;
use Throwable;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolInterface;
use NetsvrBusiness\Exception\ConnectException;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 任务socket，用于：
 * 1. business请求网关，需要网关响应指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程请求网关网关处理完毕再响应给业务进程的指令
 * 2. business请求网关，不需要网关响应指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程单向请求网关的指令
 */
class TaskSocket implements TaskSocketInterface
{
    protected string $host;
    protected int $port;
    protected float $connectTimeout;
    protected int $serverId;
    protected int $packageMaxLength;
    protected ?Coroutine\Socket $socket = null;
    protected StdoutLoggerInterface $logger;
    protected TaskSocketPoolInterface $pool;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        string                  $host,
        int                     $port,
        float                   $connectTimeout,
        int                     $serverId,
        int                     $packageMaxLength,
        TaskSocketPoolInterface $pool,
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->connectTimeout = $connectTimeout;
        $this->serverId = $serverId;
        $this->packageMaxLength = $packageMaxLength;
        $this->pool = $pool;
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $this->connect();
    }

    /**
     * @param bool $throwErr
     * @return void
     */
    protected function connect(bool $throwErr = true): void
    {
        $socket = new Coroutine\Socket(2, 1, 0);
        $socket->setProtocol([
            'open_length_check' => true,
            //大端序，详情请看：https://github.com/buexplain/netsvr/blob/main/internal/worker/manager/connProcessor.go#L127
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            /**
             * 因为网关的包头包体协议的包头描述的长度是不含包头的，所以偏移4个字节
             * @see https://github.com/buexplain/netsvr/blob/main/README.md#业务进程与本网关之间的TCP数据包边界处理
             */
            'package_body_offset' => 4,
            'package_max_length' => $this->packageMaxLength,
        ]);
        $socket->connect($this->host, $this->port, $this->connectTimeout);
        if ($socket->errCode != 0) {
            if ($throwErr) {
                throw new ConnectException($socket->errMsg, $socket->errCode);
            } else {
                $socket->close();
                return;
            }
        }
        $this->socket = $socket;
        $this->logger->debug(sprintf('TaskSocket %s:%s connect ok.', $this->host, $this->port));
    }

    /**
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        //大端序，详情请看：https://github.com/buexplain/netsvr/blob/main/internal/worker/manager/connProcessor.go#L211
        $retry = 0;
        loop:
        if ($this->socket->send(pack('N', strlen($data)) . $data) === false) {
            if ($retry > 0) {
                Coroutine::sleep(1);
            }
            $retry += 1;
            $this->connect($retry == 3);
            goto loop;
        }
    }

    public function __destruct()
    {
        try {
            $this->socket?->close();
            $this->logger->debug(sprintf('TaskSocket %s:%s close ok.', $this->host, $this->port));
        } catch (Throwable) {
        }
    }

    /**
     * @return Router|false
     * @throws Exception
     */
    public function receive(): Router|false
    {
        $data = $this->socket->recvPacket();
        //读取失败了
        if ($data === '' || $data === false) {
            return false;
        }
        //丢弃掉前4个字节，因为这4个字节是包头
        $data = substr($data, 4);
        //读取到了心跳
        if ($data === Constant::PONG_MESSAGE) {
            return false;
        }
        $router = new Router();
        $router->mergeFromString($data);
        return $router;
    }

    public function release(): void
    {
        $this->pool->release($this);
    }
}
