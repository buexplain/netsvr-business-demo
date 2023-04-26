<?php

declare(strict_types=1);

use App\Controller\WebsocketTestController;
use Hyperf\Context\ApplicationContext;
use Netsvr\Cmd;
use NetsvrBusiness\Contract\DispatcherFactoryInterface;

$dispatcher = ApplicationContext::getContainer()->get(DispatcherFactoryInterface::class)->get();

$dispatcher->addRoute(Cmd::ConnOpen, [WebsocketTestController::class, 'onOpen']);
$dispatcher->addRoute(Cmd::Transfer, [WebsocketTestController::class, 'onMessage']);
$dispatcher->addRoute(Cmd::ConnClose, [WebsocketTestController::class, 'onClose']);