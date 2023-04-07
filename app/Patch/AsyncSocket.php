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
    protected bool $running = true;

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
        //强制关闭
        if ($grace === false) {
            $this->running = false;
            $this->heartbeat->push(1);
            $this->receiveCh->close();
            $this->sendCh->close();
            $this->socket->close();
            return;
        }
        $this->heartbeat->push(1);
        //当channel里面的数据都空了，则关闭连接
        while ($this->running) {
            Coroutine::sleep(0.1);
            var_dump($this->receiveCh->length() . '--' . $this->sendCh->length());
            if ($this->receiveCh->isEmpty() && $this->sendCh->isEmpty()) {
                //不再从远端读入数据
                $this->receiveCh->close();
                //不再产生新数据，并发送数据到远端
                $this->sendCh->close();
                //关闭底层的连接
                $this->socket->close();
                $this->running = false;
            }
        }
    }

    /**
     * 定时心跳
     * @param float $heartbeatInterval 单位秒
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
            while ($this->running) {
                $data = $this->sendCh->pop();
                if ($data === false) {
                    Coroutine::sleep(0.1);
                    continue;
                }
                $this->socket->send($data, -1);
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
            while ($this->running) {
                $data = $this->socket->receive();
                if ($data === '') {
                    Coroutine::sleep(0.1);
                    continue;
                }
                $this->receiveCh->push($data);
            }
            echo date('Y-m-d H:i:s ') . 'Coroutine:loopReceive exit' . PHP_EOL;
        });
    }
}