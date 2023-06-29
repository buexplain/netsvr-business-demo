<?php

declare(strict_types=1);

use App\Controller\WebsocketController;
use App\Middleware\CheckCmdMiddleware;
use App\Middleware\LoggerMiddleware;
use App\Middleware\TokenMiddleware;
use Hyperf\Context\ApplicationContext;
use Netsvr\Cmd;
use NetsvrBusiness\Contract\DispatcherInterface;

$dispatcher = ApplicationContext::getContainer()->get(DispatcherInterface::class);

//客户端的所有请求都会经过日志中间件
$dispatcher->addRouteGroup(LoggerMiddleware::class, function (DispatcherInterface $dispatcher) {
    $dispatcher->addRoute(Cmd::ConnOpen, [WebsocketController::class, 'onOpen']);
    $dispatcher->addRoute(Cmd::ConnClose, [WebsocketController::class, 'onClose']);
    //除了连接的打开与关闭，其它所有请求都要经过token校验的中间件
    $dispatcher->addRouteGroup(TokenMiddleware::class, function () use ($dispatcher) {
        $dispatcher->addRoute(\App\Protocol\Cmd::SINGLE_CAST, [WebsocketController::class, 'singleCast']);
        //广播与群发需要校验是否有发送的权限
        $dispatcher->addRouteGroup(CheckCmdMiddleware::class, function () use ($dispatcher) {
            $dispatcher->addRoute(\App\Protocol\Cmd::BROADCAST, [WebsocketController::class, 'broadcast']);
            $dispatcher->addRoute(\App\Protocol\Cmd::GROUP_CHAT_FOR_SEND, [WebsocketController::class, 'groupChatForSend']);
        });
        $dispatcher->addRoute(\App\Protocol\Cmd::GROUP_CHAT_FOR_ATTACH, [WebsocketController::class, 'groupChatForAttach']);
        $dispatcher->addRoute(\App\Protocol\Cmd::GROUP_CHAT_FOR_DETACH, [WebsocketController::class, 'groupChatForDetach']);
    });
});
