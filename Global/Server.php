<?php

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require_once 'vendor/autoload.php';
require_once('../CLPP/MessageComponentCLPP.php');
require_once('../GTPP/MessageComponentGTPP.php');

$MessageComponent = new MessageComponent();

$server = IoServer::factory(
    new HttpServer(
        new WsServer($MessageComponent)),
        9191 //Definir qual porta (3333 ou 9191)
    );


$server->run();