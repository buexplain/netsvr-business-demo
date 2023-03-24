<?php

namespace App\Patch;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class MainSocketManager
{
    protected ?Channel $receiveCh = null;

    public function __construct()
    {
        $this->receiveCh = new Channel();
    }

    /**
     * @var array |MainSocket[]
     */
    protected array $sockets = [];

    public function add(MainSocket $socket): void
    {
        $this->sockets[$socket->getServerId()] = $socket;
        Coroutine::create(function () use ($socket) {
            while (true) {
                $data = $socket->receive();
                if ($data === false) {
                    unset($this->sockets[$socket->getServerId()]);
                    if (count($this->sockets) == 0) {
                        $this->receiveCh->close();
                    }
                    break;
                }
                $this->receiveCh->push($data);
            }
        });
    }

    public function register(): void
    {
        foreach ($this->sockets as $socket) {
            $socket->register();
        }
    }

    public function unregister(): void
    {
        foreach ($this->sockets as $socket) {
            $socket->unregister();
        }
    }

    /**
     * 读取
     * @return string|bool
     */
    public function receive(): string|bool
    {
        return $this->receiveCh->pop();
    }

    public function close(): void
    {
        foreach ($this->sockets as $socket) {
            $socket->close();
        }
    }

    /**
     * 写入
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        foreach ($this->sockets as $socket) {
            $socket->send($data);
        }
    }
}