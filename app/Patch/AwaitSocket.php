<?php
declare(strict_types=1);

namespace App\Patch;

use App\Patch\Exception\ConnectException;
use Swoole\Coroutine;

/**
 * 业务进程与网关进程的连接对象
 */
class AwaitSocket
{
    protected ?Coroutine\Socket $socket = null;
    protected bool $closed = false;
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
        $this->closed = true;
        $this->socket?->close();
    }

    /**
     * @return void
     * @throws ConnectException
     */
    public function connect(): void
    {
        if ($this->closed === true) {
            return;
        }
        $this->socket?->close();
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
            throw new ConnectException($socket->errMsg, $socket->errCode);
        }
        $this->socket = $socket;
        echo date('Y-m-d H:i:s ') . sprintf('Socket %s:%s connect ok%s', $this->host, $this->port, PHP_EOL);
    }

    public function getHostPort(): array
    {
        return ['host' => $this->host, 'port' => $this->port];
    }

    /**
     * 写入
     * @param string $data 发送的数据
     * @param int $retry 发送失败后的重试次数 -1表示无限重试，大于0表示重试次数
     * @return void
     */
    public function send(string $data, int $retry = 3): void
    {
        //大端序发送，包头只描述包体长度
        $data = pack('N', strlen($data)) . $data;
        loop:
        $ret = $this->socket->send($data);
        if ($ret !== false) {
            return;
        }
        while ($this->closed !== true && ($retry === -1 || $retry > 0)) {
            if ($retry !== -1) {
                $retry--;
            }
            Coroutine::sleep(3);
            try {
                $this->connect();
                if ($this->closed === true) {
                    $this->socket?->close();
                } else {
                    goto loop;
                }
            } catch (ConnectException) {
                continue;
            }
        }
    }

    /**
     * 读取
     * @return string
     */
    public function receive(): string
    {
        $data = $this->socket->recvPacket();
        if (is_string($data)) {
            //收到一个完整的包后，丢弃掉前4个字节，因为这4个字节是包头，这里返回包体即可
            return substr($data, 4);
        }
        return '';
    }
}