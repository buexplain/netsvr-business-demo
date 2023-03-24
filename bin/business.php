<?php

use App\Controller\IndexController;
use App\Patch\MainSocket;
use App\Patch\MainSocketManager;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Constant;
use Netsvr\Router;
use Netsvr\Transfer;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;
use App\Patch\AsyncSocket;
use App\Patch\AwaitSocket;

require 'vendor/autoload.php';

run(function () {
    //这里假设有多个网关机器
    $config = [
        //网关1
        [
            'host' => '127.0.0.1',
            'port' => 6061,
            'serverId' => 0,
            'heartbeatInterval' => 30,
            'workerId' => 1,
            'processCmdGoroutineNum' => 1,
        ],
        //网关2
        [
            'host' => '127.0.0.1',
            'port' => 7061,
            'serverId' => 1,
            'heartbeatInterval' => 30,
            'workerId' => 1,
            'processCmdGoroutineNum' => 1,
        ],
    ];
    //连接所有的网关机器
    $manager = new MainSocketManager();
    foreach ($config as $item) {
        $awaitSocket = new AwaitSocket($item['host'], $item['port']);
        $asyncSocket = new AsyncSocket($awaitSocket, $item['heartbeatInterval']);
        $main = new MainSocket($asyncSocket, $item['serverId'], $item['workerId'], $item['processCmdGoroutineNum']);
        $manager->add($main);
    }
    //向网关发起注册
    $manager->register();
    //监听三类系统信号
    $signal = false;
    foreach ([SIGINT, SIGTERM, SIGQUIT] as $item) {
        Swoole\Coroutine::create(function () use (&$signal, $item) {
            while ($signal === false) {
                if (Swoole\Coroutine\System::waitSignal($item, 1)) {
                    $signal = true;
                    break;
                }
            }
        });
    }
    //收到系统信号，开始关闭与网关的连接
    Swoole\Coroutine::create(function () use (&$signal, $manager) {
        $ch = new Coroutine\Channel();
        while ($signal === false) {
            $ch->pop(1);
        }
        $manager->unregister();
        $manager->close();
    });
    //不断的从网关读取数据，并分发到对应的控制器
    while (true) {
        $data = $manager->receive();
        if ($data === false) {
            //所有与网关的连接都断开了，这种情况是所有网关机器都挂了
            $signal = true;
            break;
        }
        //收到心跳包，忽略它
        if ($data == Constant::PONG_MESSAGE) {
            continue;
        }
        //收到新数据，开一个协程去处理
        Coroutine::create(function () use ($manager, $main, $data) {
            $router = new Router();
            $router->mergeFromString($data);
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
                echo "未适配的命令：" . $router->getCmd(), PHP_EOL;
            }
        });
    }
});