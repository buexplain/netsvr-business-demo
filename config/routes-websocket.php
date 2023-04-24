<?php

declare(strict_types=1);

use App\Controller\WebsocketController;
use Hyperf\Context\ApplicationContext;
use Netsvr\Cmd;
use NetsvrBusiness\Contract\DispatcherInterface;

$dispatcher = ApplicationContext::getContainer()->get(DispatcherInterface::class);

$dispatcher->addRoute(Cmd::ConnOpen, [WebsocketController::class, 'onOpen']);
$dispatcher->addRoute(Cmd::ConnClose, [WebsocketController::class, 'onClose']);
$dispatcher->addRoute(\App\Protocol\Cmd::BROADCAST, [WebsocketController::class, 'broadcast']);
$dispatcher->addRoute(\App\Protocol\Cmd::SINGLE_CAST, [WebsocketController::class, 'singleCast']);
