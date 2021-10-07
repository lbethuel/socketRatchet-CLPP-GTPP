<?php

use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;

//CLPP
require_once('../../DAO/CLPP/Group.php');
require_once('../../DAO/CLPP/Message.php');
require_once('../CLPP/MessageComponentCLPP.php');
//GTPP
require_once('../DAO/GTPP/Task_User.php');
require_once('../DAO/GTPP/Message.php');
require_once('../DAO/GTPP/Notify.php');
require_once('../Model/GTPP/Message.php');
require_once('../Model/GTPP/Notify.php');
require_once('../GTPP/MessageComponentGTPP.php');
//CCPP
require_once('../../DAO/CCPP/User.php');
//Utils
require_once('../Utils/UTF8.php');

require __DIR__ . '/vendor/autoload.php'; 


class MessageComponent implements MessageComponentInterface
{

  public function __construct()
  {
    system("clear");
    echo "----------------------" . PHP_EOL;
    echo "Initializing server..." . PHP_EOL;
    try {
      $this->connections = new SplObjectStorage();
    } catch (Exception $e) {
      echo "Error (__construct): " . $e->getMessage();
    }
    echo "----------------------" . PHP_EOL;
  }

  function onOpen(ConnectionInterface $conn)
  {
    echo "----------------------" . PHP_EOL;
    echo "New connection..." . PHP_EOL;
    try {
      $this->connections->attach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
    } catch (Exception $e) {
      echo "Error (onOpen): " . $e->getMessage();
    }
    echo "----------------------" . PHP_EOL;
  }

  function onClose(ConnectionInterface $conn)
  {
    echo "----------------------" . PHP_EOL;
    echo "End connection..." . PHP_EOL;
    try {

      echo "User: " . $this->connections[$conn]['user'] . PHP_EOL;
    } catch (Exception $e) {
      echo "Error (onClose): " . $e->getMessage() . PHP_EOL;
    }
    echo "----------------------" . PHP_EOL;

    $this->connections->detach($conn);
    $count = $this->connections->count();
    echo "Count: " . $count . PHP_EOL;
  }

  function onError(ConnectionInterface $conn, Exception $e)
  {
    echo "----------------------" . PHP_EOL;
    echo "Error: " . $e->getMessage();
    echo "----------------------" . PHP_EOL;
    $conn;
  }

  public function onMessage(ConnectionInterface $conn, MessageInterface $msg)
  {
    try {
      if ($msg == '') {
        echo "----------------------" . PHP_EOL;
        echo 'Empty JSON' . PHP_EOL;
        echo "----------------------" . PHP_EOL;
        $conn->send((string)json_encode(array("error" => true, "message" => "(JSON) is broken")));
        return;
      }

      if ($msg == "__ping__") {
        $conn->send((string)"__pong__");
        return;
      }







      $Clpp = new MessageComponentCLPP();

      $jsonBody = json_decode($msg, true);

      if ($this->connections[$conn] == null) {
        $this->SetConnection($conn, $jsonBody);
        return;
      }

      if ($jsonBody['aplication'] == 13 || $jsonBody['aplication'] == 7) {

        if (!isset($jsonBody['type'])) {
          echo "----------------------" . PHP_EOL;
          echo "(type) is broken" . PHP_EOL;
          echo "----------------------" . PHP_EOL;

          $conn->send((string)json_encode(array("error" => true, "message" => "(type) is broken")));
          return;
        }

        if ($jsonBody['type'] == 3) {
          if (isset($jsonBody['group_id']) && !isset($jsonBody['send_id'])) {

            $user = $Clpp->GetUsersGroup($conn, $jsonBody['group_id']);

            if ($user['error']) { //msg de erro
              return;
            }

            $data = array(
              'group_id' => $jsonBody['group_id'],
              'user_id' => $user['data']
            );

            $response = $Clpp->sendNotify($conn, $data);

            if ($response['error']) {
              echo "----------------------" . PHP_EOL;
              echo $response['message'] . PHP_EOL;
              echo "----------------------" . PHP_EOL;

              $conn->send((string)json_encode(array(
                "error" => true,
                "message" => $response['message'],
                "Notify" => "erro notification"
              )));
              return;
            }

            echo "----------------------" . PHP_EOL;
            echo "notification group id" . $jsonBody['group_id'] . PHP_EOL;
            echo $response['notify'] . PHP_EOL;
            echo "----------------------" . PHP_EOL;

            $conn->send((string)json_encode($response));

            return;
          }
        }
      }
    } catch (Exception $e) {

      echo "----------------------" . PHP_EOL;
      echo "Error (onMessage): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $e->getMessage()
      )));
    }
  }

  ///function Connect user name, id 
  private function setConnection($conn, $jsonBody)
  {

    if (!isset($jsonBody['auth']) || !isset($jsonBody['app_id'])) {
      echo "----------------------" . PHP_EOL;
      echo "(auth, app_id) is broken" . PHP_EOL;
      echo "End connection..." . PHP_EOL;
      $this->connections->detach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array("error" => true, "message" => "(auth, app_id) is broken")));
      return;
    }

    $auth = $jsonBody['auth'];
    $app_id = $jsonBody['app_id'];

    $daoUser = new DAOUser();

    $response = $daoUser->SelectSession($auth, $app_id);

    if ($response['error']) {
      if ($response['message'] == "No data") {

        $conn->send((string)json_encode(array(
          "error" => true,
          "message" => "This key is unauthorized SetConnection"
        )));

        echo "----------------------" . PHP_EOL;
        echo "This key is unauthorized: " . $auth . PHP_EOL;
        echo "For Application ID: " . $app_id . PHP_EOL;
        echo "For User ID: Unknown" . PHP_EOL;
        echo "For User Name: Unknown" . PHP_EOL;
        echo "End connection..." . PHP_EOL;
        $this->connections->detach($conn);
        $count = $this->connections->count();
        echo "Count: " . $count . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        return;
      }

      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $response['message']
      )));
      return;
    }

    //Verify if this user is connected
    foreach ($this->connections as $connection) {
      if ($this->connections[$connection]['id'] == $response["data"][0]->id) {
        $connection->send((string)json_encode(array(
          "error" => true,
          "message" => "This user has been connected to another place"
        )));

        echo "----------------------" . PHP_EOL;
        echo "This key is unauthorized: " . $this->connections[$connection]['auth'] . PHP_EOL;
        echo "For Application ID: " . $this->connections[$connection]['app_id'] . PHP_EOL;
        echo "This user has been connected to another place" . PHP_EOL;
        echo "End connection..." . PHP_EOL;
        $this->connections->detach($connection);
        $count = $this->connections->count();
        echo "Count: " . $count . PHP_EOL;
        echo "----------------------" . PHP_EOL;
      }
    }

    $data = array(
      "id" => $response["data"][0]->id,
      "user" => $response["data"][0]->name,
      "auth" => $auth,
      "app_id" => $app_id
    );

    $this->connections->offsetSet($conn, $data);
    // $this->NotifyConnectionState($data, "connected");

    echo "----------------------" . PHP_EOL;
    echo "New connection for " . $data['user'] . PHP_EOL;
    echo "----------------------" . PHP_EOL;
  }
}



