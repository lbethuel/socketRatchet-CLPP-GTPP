<?php

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;


require_once('MessageComponent.php');
require_once 'vendor/autoload.php';

$MessageComponent = new MessageComponent();

$server = IoServer::factory(
    new HttpServer(
        new WsServer($MessageComponent)),
        9191
    );

$server->run();