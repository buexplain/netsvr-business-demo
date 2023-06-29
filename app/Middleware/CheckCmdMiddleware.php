<?php

namespace App\Middleware;

use Netsvr\Transfer;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Dispatcher\MiddlewareHandler;
use NetsvrBusiness\NetBus;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 *  校验用户是否可以发送某个命令的中间件
 */
class CheckCmdMiddleware
{
    /**
     * @param MiddlewareHandler $handler
     * @param Transfer $transfer
     * @param RouterInterface $clientRouter
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(MiddlewareHandler $handler, Transfer $transfer, RouterInterface $clientRouter): void
    {
        $session = json_decode($transfer->getSession(), true);
        if (!in_array($clientRouter->getCmd(), $session['auth'])) {
            NetBus::singleCast($transfer->getUniqId(), '暂无权限！');
            return;
        }
        $handler->handle();
    }
}