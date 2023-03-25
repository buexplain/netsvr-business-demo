<?php

namespace App\Patch;

use Netsvr\Cmd;
use Netsvr\Register;
use Netsvr\Router;

/**
 * 与网关连接的业务socket
 */
class MainSocket
{
    protected ?AsyncSocket $socket = null;
    protected int $serverId = 0;
    protected int $workerId = 0;
    protected int $processCmdGoroutineNum = 0;

    public function __construct(AsyncSocket $socket, int $serverId, int $workerId, int $processCmdGoroutineNum)
    {
        $this->socket = $socket;
        $this->serverId = $serverId;
        $this->workerId = $workerId;
        $this->processCmdGoroutineNum = $processCmdGoroutineNum;
    }

    /**
     * 读取
     * @return string|bool
     */
    public function receive(): string|bool
    {
        return $this->socket->receive();
    }

    public function getServerId(): int
    {
        return $this->serverId;
    }

    /**
     * 注册到网关进程
     * @return bool
     */
    public function register(): bool
    {
        $router = new Router();
        $router->setCmd(Cmd::Register);
        $reg = new Register();
        $reg->setId($this->workerId);
        $reg->setProcessCmdGoroutineNum($this->processCmdGoroutineNum);
        $router->setData($reg->serializeToString());
        return $this->socket->send($router->serializeToString());
    }

    /**
     * 取消注册到网关进程
     * @return void
     */
    public function unregister(): void
    {
        $router = new Router();
        $router->setCmd(Cmd::Unregister);
        $this->socket->send($router->serializeToString());
    }

    /**
     * 写入
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        $this->socket->send($data);
    }

    public function close(): void
    {
        $this->socket->close(true);
    }
}