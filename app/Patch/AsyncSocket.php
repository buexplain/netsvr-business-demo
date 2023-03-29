<?php
declare(strict_types=1);

namespace App\Patch;

use Netsvr\Constant;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 对socket用channel封装，支持多协程的并发读写
 */
class AsyncSocket
{
    protected ?Channel $heartbeat = null;
    protected ?Channel $sendCh = null;
    protected ?Channel $receiveCh = null;
    protected ?AwaitSocket $socket = null;
    protected bool $closed = false;

    public function __construct(AwaitSocket $socket, float $heartbeatInterval = 30)
    {
        $this->socket = $socket;
        $this->loopHeartbeat($heartbeatInterval);
        $this->loopSend();
        $this->loopReceive();
    }

    public function getHostPort(): array
    {
        return $this->socket->getHostPort();
    }

    /**
     * 写入
     * @param string $data
     * @return bool
     */
    public function send(string $data): bool
    {
        return $this->sendCh->push($data);
    }

    /**
     * 读取
     * @return string|bool
     */
    public function receive(): string|bool
    {
        return $this->receiveCh->pop();
    }

    /**
     * 关闭连接
     * @param bool $grace
     * @return void
     */
    public function close(bool $grace): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        //关闭心跳
        $this->heartbeat->push(1);
        //强制关闭
        if ($grace === false) {
            $this->receiveCh->close();
            $this->sendCh->close();
            $this->socket->close();
            return;
        }
        //当channel里面的数据都空了，则关闭连接
        while (true) {
            Coroutine::sleep(0.1);
            if ($this->receiveCh->isEmpty() && $this->sendCh->isEmpty()) {
                //不再从远端读入数据
                $this->receiveCh->close();
                //不再产生新数据，并发送数据到远端
                $this->sendCh->close();
                //关闭底层的连接
                $this->socket->close();
                break;
            }
        }
    }

    /**
     * 定时心跳
     * @param float $heartbeatInterval
     * @return void
     */
    protected function loopHeartbeat(float $heartbeatInterval): void
    {
        if ($this->heartbeat) {
            return;
        }
        $this->heartbeat = new Channel(1);
        Coroutine::create(function () use ($heartbeatInterval) {
            while ($this->heartbeat->pop($heartbeatInterval) === false) {
                $this->sendCh->push(Constant::PING_MESSAGE);
            }
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopHeartbeat exit' . PHP_EOL;
        });
    }

    /**
     * @return void
     */
    protected function loopSend(): void
    {
        if ($this->sendCh) {
            return;
        }
        $this->sendCh = new Channel(100);
        Coroutine::create(function () {
            while (true) {
                $data = $this->sendCh->pop();
                if ($data === false) {
                    break;
                }
                $this->socket->send($data);
            }
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopSend exit' . PHP_EOL;
        });
    }

    /**
     * @return void
     */
    protected function loopReceive(): void
    {
        if ($this->receiveCh) {
            return;
        }
        $this->receiveCh = new Channel(100);
        Coroutine::create(function () {
            while (true) {
                $data = $this->socket->receive();
                if ($data === false || $data === '') {
                    //底层连接挂了，强制关闭自己
                    $this->close(false);
                    break;
                } else if ($this->receiveCh->push($data) === false) {
                    break;
                }
            }
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopReceive exit' . PHP_EOL;
        });
    }
}