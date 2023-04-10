<?php

namespace App\Command\Worker;

use App\Command\Worker\Exception\DuplicateServerIdException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Netsvr\Router;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

class WorkerSocketManager
{
    protected ?Channel $receiveCh = null;
    protected StdoutLoggerInterface $logger;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        $this->receiveCh = new Channel();
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    /**
     * @var WorkerSocket[]
     */
    protected array $sockets = [];

    public function add(WorkerSocket $socket): void
    {
        if (isset($this->sockets[$socket->getServerId()])) {
            throw new DuplicateServerIdException('Duplicate ServerId: ' . $socket->getServerId());
        }
        $this->sockets[$socket->getServerId()] = $socket;
        //这里做一到中转，将每个socket发来的数据统一转发到一个channel里面
        Coroutine::create(function () use ($socket) {
            while (true) {
                $data = $socket->receive();
                if ($data !== false) {
                    $this->receiveCh->push($data);
                    continue;
                }
                $this->logger->debug('Coroutine:loopTransfer exit');
                unset($this->sockets[$socket->getServerId()]);
                if (count($this->sockets) == 0) {
                    $this->receiveCh->close();
                }
                break;
            }
        });
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function register(): void
    {
        $wg = new Coroutine\WaitGroup();
        $e = null;
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket, &$e) {
                try {
                    $socket->register();
                } catch (Throwable $throwable) {
                    $e = $throwable;
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
        if ($e) {
            throw $e;
        }
        foreach ($this->sockets as $socket) {
            $socket->loopSend();
            $socket->loopHeartbeat();
        }
    }

    public function unregister(): void
    {
        $wg = new Coroutine\WaitGroup();
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket) {
                try {
                    $socket->unregister();
                    $socket->waitUnregisterOk();
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    /**
     * 读取
     * @return Router|bool
     */
    public function receive(): Router|bool
    {
        return $this->receiveCh->pop();
    }

    public function close(): void
    {
        $wg = new Coroutine\WaitGroup();
        foreach ($this->sockets as $socket) {
            $wg->add();
            Coroutine::create(function () use ($wg, $socket) {
                try {
                    $socket->close();
                } catch (Throwable) {
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    /**
     * 写入到当前所有网关连接中
     * @param string $data
     * @return void
     */
    public function send(string $data): void
    {
        foreach ($this->sockets as $socket) {
            $socket->send($data);
        }
    }

    /**
     * 返回某个网关连接
     * @param int $serverId
     * @return WorkerSocket|null
     */
    public function getSocket(int $serverId): ?WorkerSocket
    {
        return $this->sockets[$serverId] ?? null;
    }
}