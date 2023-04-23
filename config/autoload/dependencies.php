<?php

use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Router\JsonRouter;

return [
    //将路由接口替换为json版，这样客户端在打包业务数据的时候就必须用该路由指定的格式打包
    RouterInterface::class => JsonRouter::class,
];