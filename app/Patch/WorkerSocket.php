<?php

namespace App\Patch;

use App\Patch\Exception\ConnectException;
use Exception;
use Netsvr\Cmd;
use Netsvr\Constant;
use Netsvr\Register;
use Netsvr\Router;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

class WorkerSocket
{
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
    }

    private function makeRegisterProtocol(): string
    {
        $router = new Router();
        $router->setCmd(Cmd::Register);
        $reg = new Register();
        $reg->setId($this->workerId);
        $reg->setProcessCmdGoroutineNum($this->processCmdGoroutineNum);
        $router->setData($reg->serializeToString());
        return $router->serializeToString();
    }

    private function makeSocket(): Coroutine\Socket
    {
        $socket = new Coroutine\Socket(2, 1, 0);
        $socket->setProtocol([
            'open_length_check' => true,
            //大端序
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            /**
             * 因为网关的包头包体协议的包头描述的长度是不含包头的，所以偏移4个字节
             * @see https://github.com/buexplain/netsvr
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
            var_dump('拿锁失败');
            return;
        }
        if ($this->running === false) {
            $this->repairMux->push(1);
            return;
        }
        if ($this->socket && $this->socket->checkLiveness() === true) {
            var_dump('还活着');
            $this->repairMux->push(1);
            return;
        }
        var_dump('进入修复逻辑' . $this->sendCh->length());
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
                    echo date('Y-m-d H:i:s ') . sprintf('Socket %s:%s connect ok%s', $this->host, $this->port, PHP_EOL);
                    $this->socket = $socket;
                    break;
                }
                if ($socket->send($data) !== false) {
                    $this->socket = $socket;
                    echo date('Y-m-d H:i:s ') . sprintf('Socket %s:%s connect and register ok%s', $this->host, $this->port, PHP_EOL);
                    break;
                }
                $socket->close();
                Coroutine::sleep(3);
                var_dump('尝试再次修复');
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
        echo date('Y-m-d H:i:s ') . sprintf('Socket %s:%s connect ok%s', $this->host, $this->port, PHP_EOL);
    }

    private function _send(string $data): int|false
    {
        Coroutine::sleep(1);
        return $this->socket->send(pack('N', strlen($data)) . $data);
    }

    /**
     * 注册到网关进程
     */
    public function register(): bool
    {
        if ($this->_send($this->makeRegisterProtocol()) !== false) {
            if (!$this->waitUnregisterOk) {
                $this->waitUnregisterOk = new Channel(1);
            }
            return true;
        }
        return false;
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
            Coroutine::sleep(5);
            var_dump('sendCh --> ' . $this->sendCh->length() . date(' H:i:s'));
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
        //读取到了心跳，或者是空字符串，则重新读取
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
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopHeartbeat exit' . PHP_EOL;
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
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopSend exit' . PHP_EOL;
        });
    }

    public function getServerId(): int
    {
        return $this->serverId;
    }
}