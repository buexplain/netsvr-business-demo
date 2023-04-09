<?php

use App\Controller\IndexController;
use App\Patch\WorkerSocket;
use App\Patch\WorkerSocketManager;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

require 'vendor/autoload.php';
run(function () {
    //这里可以配置多个网关机器
    $config = [
        //网关1
//        [
//            'host' => '127.0.0.1',
//            'port' => 7061,
//            'serverId' => 0,
//            'heartbeatInterval' => 30,
//            'workerId' => 1,
//            'processCmdGoroutineNum' => 1,
//        ],
        //let ws = new WebSocket("ws://127.0.0.1:6060/netsvr?admin=1");ws.onmessage = function() {};let setIntervalIndex = 0;ws.onclose = function() {clearInterval(setIntervalIndex)};setIntervalIndex = setInterval(function() {ws.send("001dfsdfg")}, 100);
        [
            'host' => '127.0.0.1',
            'port' => 6061,
            'serverId' => 1,
            'heartbeatInterval' => 30,
            'workerId' => 1,
            'processCmdGoroutineNum' => 1,
        ],
    ];
    //连接所有的网关机器
    $manager = new WorkerSocketManager();
    try {
        foreach ($config as $item) {
            $socket = new WorkerSocket(
                $item['host'],
                $item['port'],
                1,
                $item['serverId'],
                $item['workerId'],
                $item['processCmdGoroutineNum'],
                30,
                2 * 2 * 1024
            );
            $socket->connect();
            $manager->add($socket);
        }
    } catch (Throwable $throwable) {
        echo sprintf(
            "%d --> %s in %s on line %d%s",
            $throwable->getCode(),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            PHP_EOL
        );
        exit(1);
    }
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
    //向网关发起注册
    $manager->register();
    //收到系统信号，开始关闭与网关的连接
    Swoole\Coroutine::create(function () use (&$signal, $manager) {
        $ch = new Coroutine\Channel();
        while ($signal === false) {
            $ch->pop(1);
        }
        var_dump('开始取消注册');
        $manager->unregister();
        var_dump('开始关闭');
        $manager->close();
    });
    //TODO 删除这个
    Swoole\Coroutine::create(function () use (&$signal) {
        sleep(30);
        var_dump('发出关闭信号');
        $signal = true;
    });
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
                echo "未适配的命令：" . $router->getCmd(), PHP_EOL;
            }
        });
    }
});