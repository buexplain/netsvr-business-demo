<?php

use App\Controller\IndexController;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Constant;
use Netsvr\Register;
use Netsvr\Router;
use Netsvr\Transfer;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;
use App\Patch\AsyncSocket;
use App\Patch\Socket;

require 'vendor/autoload.php';

run(function () {
    //监听系统信号
    $running = true;
    Swoole\Coroutine::create(function () use (&$running) {
        Swoole\Coroutine\System::waitSignal(SIGTERM, -1);
        $running = false;
    });
    //连接到网关进程
    $socket = new Socket('127.0.0.1', 6061);
    //注册到网关进程
    $router = new Router();
    $router->setCmd(Cmd::Register);
    $reg = new Register();
    $workerId = 1;
    $reg->setId($workerId);
    $reg->setProcessCmdGoroutineNum(3);
    $router->setData($reg->serializeToString());
    $socket->send($router->serializeToString());
    echo '注册到网关进程ok，workerId --> ' . $workerId, PHP_EOL;
    //构造一个异步读写的socket
    $main = new AsyncSocket($socket, 1);
    //不断的读取数据，并分发到对应的控制器
    while ($running) {
        $data = $main->receive();
        //收到心跳包，忽略它
        if ($data == Constant::PONG_MESSAGE) {
            continue;
        }
        //收到新数据，开一个协程去处理
        Coroutine::create(function () use ($main, $data) {
            $router = new Router();
            $router->mergeFromString($data);
            if ($router->getCmd() == Cmd::ConnOpen) {
                //连接打开的信息
                $connOpen = new ConnOpen();
                $connOpen->mergeFromString($router->getData());
                IndexController::onOpen($main, $connOpen);
            } else if ($router->getCmd() == Cmd::ConnClose) {
                //连接关闭的信息
                $connClose = new ConnClose();
                $connClose->mergeFromString($router->getData());
                IndexController::onClose($main, $connClose);
            } else if ($router->getCmd() == Cmd::Transfer) {
                //客户发送的信息
                $transfer = new Transfer();
                $transfer->mergeFromString($router->getData());
                IndexController::onMessage($main, $transfer);
            } else {
                /**
                 * 业务进程请求网关，网关处理完毕再响应给业务进程的指令
                 * @see https://github.com/buexplain/netsvr-protocol
                 */
                echo "未适配的命令：" . $router->getCmd(), PHP_EOL;
            }
        });
    }
    $main->close();
});