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
    protected bool $running = false;
    protected ?Socket $socket = null;

    public function __construct(Socket $socket, float $heartbeatInterval = 30)
    {
        $this->socket = $socket;
        $this->running = true;
        $this->loopHeartbeat($heartbeatInterval);
        $this->loopSend();
        $this->loopReceive();
    }

    /**
     * 写入
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        $this->sendCh->push($data);
    }

    /**
     * 读取
     * @return string
     */
    public function receive(): string
    {
        return $this->receiveCh->pop();
    }

    public function close(): void
    {
        if (!$this->socket) {
            return;
        }
        $this->running = false;
        $this->socket->close();
        $this->socket = null;
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
        $this->heartbeat = new Channel();
        Coroutine::create(function () use ($heartbeatInterval) {
            while ($this->running && !$this->heartbeat->pop($heartbeatInterval)) {
                $this->sendCh->push(Constant::PING_MESSAGE);
            }
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
                if ($data !== false) {
                    $this->socket->send($data);
                }
            }
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
                $this->receiveCh->push($this->socket->receive());
            }
        });
    }
}