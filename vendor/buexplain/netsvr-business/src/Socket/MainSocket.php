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

use Netsvr\RegisterReq;
use Netsvr\RegisterResp;
use Netsvr\RegisterRespCode;
use NetsvrBusiness\Contract\MainSocketInterface;
use NetsvrBusiness\Exception\ConnectException;
use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Netsvr\Cmd;
use Netsvr\Constant;
use Netsvr\Router;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * 主socket，用于：
 * 1. 接收网关单向发给business的指令，具体移步：https://github.com/buexplain/netsvr-protocol#网关单向转发给业务进程的指令
 * 2. business请求网关，无需网关响应的指令，具体移步：https://github.com/buexplain/netsvr-protocol#业务进程单向请求网关的指令
 */
class MainSocket implements MainSocketInterface
{
    public string $loggerPrefix = '';
    protected string $host;
    protected int $port;
    protected float $connectTimeout;
    protected int $serverId;
    protected int $workerId;
    protected int $processCmdGoroutineNum;
    protected float $heartbeatInterval;
    protected int $packageMaxLength;
    protected ?Coroutine\Socket $socket = null;
    protected ?Channel $sendCh = null;
    protected bool $running = true;
    protected ?Channel $heartbeat = null;
    protected ?Channel $waitUnregisterOk = null;
    protected ?Channel $repairMux = null;
    protected bool $closed = false;
    protected StdoutLoggerInterface $logger;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        string $host,
        int    $port,
        float  $connectTimeout,
        int    $serverId,
        int    $workerId,
        int    $processCmdGoroutineNum,
        float  $heartbeatInterval,
        int    $packageMaxLength
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->connectTimeout = $connectTimeout;
        $this->serverId = $serverId;
        $this->workerId = $workerId;
        $this->processCmdGoroutineNum = $processCmdGoroutineNum;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->packageMaxLength = $packageMaxLength;
        $this->repairMux = new Channel(1);
        $this->repairMux->push(1);
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    private function makeRegisterProtocol(): string
    {
        $router = new Router();
        $router->setCmd(Cmd::Register);
        $reg = new RegisterReq();
        $reg->setWorkerId($this->workerId);
        $reg->setServerId($this->serverId);
        $reg->setProcessCmdGoroutineNum($this->processCmdGoroutineNum);
        $router->setData($reg->serializeToString());
        return $router->serializeToString();
    }

    private function makeSocket(): Coroutine\Socket
    {
        $socket = new Coroutine\Socket(2, 1, 0);
        $socket->setProtocol([
            'open_length_check' => true,
            //大端序，详情请看：https://github.com/buexplain/netsvr/blob/main/internal/worker/manager/connProcessor.go#L127
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            /**
             * 因为网关的包头包体协议的包头描述的长度是不含包头的，所以偏移4个字节
             * @see https://github.com/buexplain/netsvr/blob/main/README.md#业务进程与网关之间的tcp数据包边界处理
             */
            'package_body_offset' => 4,
            'package_max_length' => $this->packageMaxLength,
        ]);
        return $socket;
    }

    protected function repair(): void
    {
        $lock = $this->repairMux->pop(0.02);
        if ($lock === false) {
            return;
        }
        if ($this->running === false) {
            $this->repairMux->push(1);
            return;
        }
        if ($this->socket && $this->socket->checkLiveness() === true) {
            $this->repairMux->push(1);
            return;
        }
        //如果程序启动后，立马断开，反复出现这个日志错误，则有可能是business.php的serverId配置的与网关服务配置的serverId不一致
        $this->logger->error(sprintf($this->loggerPrefix . 'repair socket %s:%s connect starting, because: %s', $this->host, $this->port, $this->socket->errMsg));
        $data = $this->makeRegisterProtocol();
        $data = pack('N', strlen($data)) . $data;
        while ($this->running === true) {
            try {
                $this->socket?->close();
                $socket = $this->makeSocket();
                $socket->connect($this->host, $this->port, $this->connectTimeout);
                if ($socket->errCode != 0) {
                    Coroutine::sleep(3);
                    continue;
                }
                if ($this->closed === true) {
                    $this->logger->debug(sprintf($this->loggerPrefix . 'repair socket %s:%s connect ok.', $this->host, $this->port));
                    $this->socket = $socket;
                    break;
                }
                if ($socket->send($data) !== false) {
                    $resp = $socket->recvPacket();
                    if ($resp === '' || $resp === false) {
                        Coroutine::sleep(3);
                        $socket->close();
                        continue;
                    }
                    $this->socket = $socket;
                    $this->logger->debug(sprintf($this->loggerPrefix . 'repair socket %s:%s connect and register ok.', $this->host, $this->port));
                    break;
                }
                $socket->close();
                $this->logger->error(sprintf($this->loggerPrefix . 'repair socket %s:%s connect failed, wait for three seconds before continuing.', $this->host, $this->port));
                Coroutine::sleep(3);
            } catch (Throwable) {
            }
        }
        $this->repairMux->push(1);
    }

    public function connect(): void
    {
        $this->socket?->close();
        $socket = $this->makeSocket();
        $socket->connect($this->host, $this->port, $this->connectTimeout);
        if ($socket->errCode != 0) {
            throw new ConnectException($socket->errMsg, $socket->errCode);
        }
        $this->socket = $socket;
        $this->logger->debug(sprintf($this->loggerPrefix . 'socket %s:%s connect ok.', $this->host, $this->port));
    }

    private function _send(string $data): int|false
    {
        //大端序，详情请看：https://github.com/buexplain/netsvr/blob/main/internal/worker/manager/connProcessor.go#L211
        return $this->socket->send(pack('N', strlen($data)) . $data);
    }

    /**
     * 注册到网关进程
     */
    public function register(): bool
    {
        if ($this->_send($this->makeRegisterProtocol()) === false) {
            return false;
        }
        //发送成功后，接收注册结果
        $data = $this->socket->recvPacket();
        if ($data === '' || $data === false) {
            //读取失败
            return false;
        }
        try {
            //解码路由，丢弃掉前4个字节，因为这4个字节是包头
            $data = substr($data, 4);
            $router = new Router();
            $router->mergeFromString($data);
            //网关服务返回了其它的指令
            if ($router->getCmd() != Cmd::Register) {
                $this->logger->error(sprintf(
                    $this->loggerPrefix . 'register socket %s:%s failed, expecting the netsvr to return a response to the register cmd.',
                    $this->host,
                    $this->port,
                ));
                return false;
            }
            //解码注册结果
            $payload = new RegisterResp();
            $payload->mergeFromString($router->getData());
            if ($payload->getCode() === RegisterRespCode::Success) {
                //注册成功
                if (!$this->waitUnregisterOk) {
                    $this->waitUnregisterOk = new Channel(1);
                }
                return true;
            }
            //注册失败
            if ($payload->getCode() === RegisterRespCode::WorkerIdOverflow) {
                $this->logger->error(sprintf(
                    $this->loggerPrefix . 'register socket %s:%s failed, workerId option in file business.php is overflow of range.',
                    $this->host,
                    $this->port,
                ));
            } else if ($payload->getCode() === RegisterRespCode::ServerIdInconsistent) {
                $this->logger->error(sprintf(
                    $this->loggerPrefix . 'register socket %s:%s failed, serverId option in file business.php is incorrect.',
                    $this->host,
                    $this->port,
                    $this->serverId
                ));
            } else {
                $this->logger->error(sprintf(
                    $this->loggerPrefix . 'register socket %s:%s failed, because: %s.',
                    $this->host,
                    $this->port,
                    $payload->getMessage()
                ));
            }
            return false;
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
            return false;
        }
    }

    /**
     * 取消注册到网关进程
     * @return void
     */
    public function unregister(): void
    {
        $router = new Router();
        $router->setCmd(Cmd::Unregister);
        $this->send($router->serializeToString());
    }

    public function waitUnregisterOk(): void
    {
        $this->waitUnregisterOk?->pop(60);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->heartbeat->push(1);
        $emptyNum = 0;
        while ($this->running) {
            Coroutine::sleep(1);
            $this->logger->debug(sprintf($this->loggerPrefix . 'closing socket %s:%s, Wait for sendCh cleaning completed: %d', $this->host, $this->port, $this->sendCh->length()));
            if ($this->sendCh->isEmpty()) {
                $emptyNum++;
            } else {
                $emptyNum = 0;
            }
            if ($emptyNum >= 3) {
                //先让协程退出
                $this->running = false;
                //不再产生新数据，并发送数据到远端
                $this->sendCh->close();
                //关闭底层的连接
                $this->socket->close();
            }
        }
    }

    public function send(string $data): void
    {
        $this->sendCh->push($data);
    }

    /**
     * @throws Exception
     */
    public function receive(): Router|false
    {
        loop:
        if ($this->running === false) {
            return false;
        }
        $data = $this->socket->recvPacket();
        //读取失败了，发起重连
        if ($data === '' || $data === false) {
            Coroutine::sleep(3);
            $this->repair();
            goto loop;
        }
        //丢弃掉前4个字节，因为这4个字节是包头
        $data = substr($data, 4);
        //读取到了心跳，则重新读取
        if ($data == Constant::PONG_MESSAGE) {
            goto loop;
        }
        $router = new Router();
        $router->mergeFromString($data);
        //收到取消注册成功的信息
        if ($router->getCmd() === Cmd::Unregister) {
            if ($this->waitUnregisterOk && $this->waitUnregisterOk->isFull() === false) {
                $this->waitUnregisterOk->push(1, 0.02);
            }
            goto loop;
        }
        return $router;
    }

    public function loopHeartbeat(): void
    {
        if ($this->heartbeat) {
            return;
        }
        $this->heartbeat = new Channel(1);
        Coroutine::create(function () {
            while ($this->heartbeat->pop($this->heartbeatInterval) === false) {
                $this->sendCh->push(Constant::PING_MESSAGE);
            }
            $this->logger->debug($this->loggerPrefix . 'Coroutine:loopHeartbeat exit.');
        });
    }

    public function loopSend(): void
    {
        if ($this->sendCh) {
            return;
        }
        $this->sendCh = new Channel(100);
        Coroutine::create(function () {
            while ($this->running) {
                $data = $this->sendCh->pop();
                if ($data === false) {
                    continue;
                }
                while ($this->_send($data) === false && $this->running) {
                    Coroutine::sleep(3);
                    $this->repair();
                }
            }
            $this->logger->debug($this->loggerPrefix . 'coroutine:loopSend exit.');
        });
    }

    public function getServerId(): int
    {
        return $this->serverId;
    }
}