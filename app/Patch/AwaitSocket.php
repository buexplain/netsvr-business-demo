<?php
declare(strict_types=1);

namespace App\Patch;

use RuntimeException;
use Swoole\Coroutine;

/**
 * 业务进程与网关进程的连接对象
 */
class AwaitSocket
{
    protected ?Coroutine\Socket $socket = null;
    protected bool $closed = true;
    protected string $host;
    protected int $port;
    protected int|float $timeout;


    public function __construct(string $host, int $port, float $timeout = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->socket->close();
        $this->closed = true;
    }

    /**
     * @return void
     */
    public function connect(): void
    {
        if (!$this->closed) {
            return;
        }
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
            'package_max_length' => 1024 * 1024 * 2,
        ]);
        $socket->connect($this->host, $this->port, $this->timeout);
        if ($socket->errCode != 0) {
            throw new RuntimeException($socket->errMsg, $socket->errCode);
        }
        $this->socket = $socket;
        $this->closed = false;
        echo date('Y-m-d H:i:s ') . sprintf('Socket %s:%s connect ok%s', $this->host, $this->port, PHP_EOL);
    }

    public function getHostPort(): array
    {
        return ['host' => $this->host, 'port' => $this->port];
    }

    /**
     * 写入
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        //大端序发送，包头只描述包体长度
        $this->socket->send(pack('N', strlen($data)) . $data);
    }

    /**
     * 读取
     * @return false|string
     */
    public function receive(): bool|string
    {
        $data = $this->socket->recvPacket();
        if (is_string($data)) {
            //收到一个完整的包后，丢弃掉前4个字节，因为这4个字节是包头，这里返回包体即可
            return substr($data, 4);
        }
        return false;
    }
}