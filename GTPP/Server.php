<?php

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require_once('../DAO/CCPP/User.php');
require_once('../Services/MessageComponent.php');
require_once 'vendor/autoload.php';

$MessageComponent = new MessageComponent();

$server = IoServer::factory(
    new HttpServer(
        new WsServer($MessageComponent)),
        3333
    );

$server->run();