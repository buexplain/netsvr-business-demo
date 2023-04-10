<?php

declare(strict_types=1);

namespace App\Command\Business;

use App\Controller\IndexController;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Throwable;

#[Command]
class StartCommand extends HyperfCommand
{
    protected StdoutLoggerInterface $logger;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('start');
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Business Start Command');
    }

    /**
     * @throws Throwable
     */
    public function handle()
    {
        $config = config('business', []);
        //连接所有的网关机器
        $manager = new WorkerSocketManager();
        try {
            foreach ($config as $item) {
                $socket = new WorkerSocket(
                    $item['host'],
                    $item['port'],
                    3,
                    $item['serverId'],
                    $item['workerId'],
                    $item['processCmdGoroutineNum'],
                    $item['heartbeatInterval'],
                    $item['packageMaxLength'],
                );
                $socket->connect();
                $manager->add($socket);
            }
        } catch (Throwable $throwable) {
            $this->logger->error(sprintf(
                "%d --> %s in %s on line %d",
                $throwable->getCode(),
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ));
            return;
        }
        //监听三类系统信号
        $signal = false;
        foreach ([SIGINT, SIGTERM, SIGQUIT] as $item) {
            Coroutine::create(function () use (&$signal, $item) {
                while ($signal === false) {
                    if (Coroutine\System::waitSignal($item, 1)) {
                        $signal = true;
                        break;
                    }
                }
            });
        }
        //向网关发起注册
        $manager->register();
        //收到系统信号，开始关闭与网关的连接
        Coroutine::create(function () use (&$signal, $manager) {
            $ch = new Coroutine\Channel();
            while ($signal === false) {
                $ch->pop(1);
            }
            $this->logger->notice('Received signal to end the process, starting to unregister of the worker process');
            $manager->unregister();
            $this->logger->notice('Unregister success, starting to close of the worker process');
            $manager->close();
            $this->logger->notice('Worker process stopped');
        });
        //TODO 删除这个
        Coroutine::create(function () use (&$signal) {
            sleep(30);
            var_dump('发出关闭信号');
            $signal = true;
        });
        $this->logger->notice('Worker process running');
        //不断的从网关读取数据，并分发到对应的控制器
        while (true) {
            $router = $manager->receive();
            if ($router === false) {
                $signal = true;
                break;
            }
            //收到新数据，开一个协程去处理
            Coroutine::create(function () use ($manager, $router) {
                if ($router->getCmd() == Cmd::ConnOpen) {
                    //连接打开的信息
                    $connOpen = new ConnOpen();
                    $connOpen->mergeFromString($router->getData());
                    IndexController::onOpen($manager, $connOpen);
                } else if ($router->getCmd() == Cmd::ConnClose) {
                    //连接关闭的信息
                    $connClose = new ConnClose();
                    $connClose->mergeFromString($router->getData());
                    IndexController::onClose($manager, $connClose);
                } else if ($router->getCmd() == Cmd::Transfer) {
                    //客户发送的信息
                    $transfer = new Transfer();
                    $transfer->mergeFromString($router->getData());
                    IndexController::onMessage($manager, $transfer);
                } else {
                    /**
                     * 业务进程请求网关，网关处理完毕再响应给业务进程的指令
                     * @see https://github.com/buexplain/netsvr-protocol
                     */
                    $this->logger->error("Unknown cmd %d" . $router->getCmd());
                }
            });
        }
    }
}
