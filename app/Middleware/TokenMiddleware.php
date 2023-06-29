<?php

namespace App\Middleware;

use Netsvr\Transfer;
use NetsvrBusiness\Contract\MiddlewareHandlerInterface;
use NetsvrBusiness\NetBus;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 校验用户是否登录的中间件
 */
class TokenMiddleware
{
    /**
     * @param MiddlewareHandlerInterface $handler
     * @param Transfer $transfer
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(MiddlewareHandlerInterface $handler, Transfer $transfer): void
    {
        if ($transfer->getSession() === '') {
            NetBus::singleCast($transfer->getUniqId(), '请先登录！');
            return;
        }
        $handler->handle();
    }
}