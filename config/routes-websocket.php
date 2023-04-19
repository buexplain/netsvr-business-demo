<?php

declare(strict_types=1);

use App\Cmd\Cmd;
use App\Controller\WebsocketController;
use Hyperf\Context\ApplicationContext;
use NetsvrBusiness\Contract\DispatcherInterface;

$dispatcher = ApplicationContext::getContainer()->get(DispatcherInterface::class);

$dispatcher->addRoute(Cmd::ConnOpen, [WebsocketController::class, 'onOpen']);
$dispatcher->addRoute(Cmd::ConnClose, [WebsocketController::class, 'onClose']);
$dispatcher->addRoute(Cmd::Broadcast, [WebsocketController::class, 'broadcast']);
$dispatcher->addRoute(Cmd::SingleCast, [WebsocketController::class, 'singleCast']);
