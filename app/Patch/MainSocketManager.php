<?php

namespace App\Patch;

use Netsvr\Cmd;
use Netsvr\Constant;
use Netsvr\Router;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

class MainSocketManager
{
    protected ?Channel $receiveCh = null;
    protected ?Channel $unregisterCh = null;
    protected int $registerCount = 0;

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
        //这里做一到中转，将每个socket发来的数据统一到一个channel里面
        Coroutine::create(function () use ($socket) {
            while (true) {
                $data = $socket->receive();
                if ($data === false) {
                    unset($this->sockets[$socket->getServerId()]);
                    if (count($this->sockets) == 0) {
                        $this->receiveCh->close();
                        $this->unregisterCh->close();
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
            if ($socket->register()) {
                $this->registerCount++;
            }
        }
        if ($this->registerCount > 0) {
            $this->unregisterCh = new Channel($this->registerCount);
        }
    }

    public function unregister(): void
    {
        foreach ($this->sockets as $socket) {
            $socket->unregister();
        }
        //等待所有已经注册连接返回取消注册的信息
        for ($i = 0; $i < $this->registerCount; $i++) {
            $this->unregisterCh->pop();
        }
    }

    /**
     * 读取
     * @return Router|bool
     */
    public function receive(): Router|bool
    {
        loop:
        $data = $this->receiveCh->pop();
        //收到心跳包，忽略它，继续读取数据
        if ($data == Constant::PONG_MESSAGE) {
            goto loop;
        }
        //channel关闭后返回false，只有当所有与网关的连接都断开了，才会关闭channel
        if ($data === false) {
            return false;
        }
        try {
            $router = new Router();
            $router->mergeFromString($data);
            //如果是收到取消注册成功的信息，则把这个消息填充到取消注册的等待channel上
            if ($router->getCmd() === Cmd::Unregister) {
                $this->unregisterCh->push(1);
                goto loop;
            }
            return $router;
        } catch (Throwable) {
            return false;
        }
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