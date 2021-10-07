<?php

use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;

require_once('../../DAO/CLPP/Group.php');
require_once('../../DAO/CLPP/Message.php');
require_once('../../DAO/CCPP/User.php');
require_once 'vendor/autoload.php';

class MessageComponent implements MessageComponentInterface
{

  protected $connections;

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
    echo "------------------" . PHP_EOL;
    echo "New Connection..." . PHP_EOL;

    try {
      $this->connections->attach($conn);
    } catch (Exception $e) {
      echo "Error (onOpen)" . $e->getMessage();
    }
    echo "------------------" . PHP_EOL;
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
  }


  function onMessage(ConnectionInterface $conn, MessageInterface $msg)
  {
    try {
      if ($msg == '') {
        echo "----------------------" . PHP_EOL;
        echo 'Empty JSON' . PHP_EOL;
        echo "----------------------" . PHP_EOL;
        $conn->send((string)json_encode(array("error" => true, "message" => "Message is broken")));
        return;
      }




      if ($msg == "__ping__") {
        $conn->send((string)"__pong__");
        return;
      }

      $jsonBody = json_decode($msg, true);

      // var_dump($jsonBody);

      if ($this->connections[$conn] == null) {
        $this->SetConnection($conn, $jsonBody);
        return;
      }



      if (!isset($jsonBody['type'])) {
        echo "----------------------" . PHP_EOL;
        echo "(type) is broken" . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        $conn->send((string)json_encode(array("error" => true, "message" => "(type) is broken")));
        return;
      }


      if ($jsonBody['type'] == 2) {


        if (isset($jsonBody['group_id']) && !isset($jsonBody['send_id']) && isset($jsonBody['last_id'])) {
          $response = $this->GetUsersGroup($conn, $jsonBody['group_id']);

          if ($response['error']) {
            return;
          }

          $data = array(
            $response['data']
          );

          $this->SendMessages($conn, $data, $jsonBody['last_id']);
          return;
        }

        if (!isset($jsonBody['group_id']) && isset($jsonBody['send_id']) && isset($jsonBody['last_id'])) {

          $data = array(
            $jsonBody['send_id']
          );
          $this->SendMessages($conn, $data, $jsonBody['last_id']);
          return;
        }


        return;
      }


      if ($jsonBody['type'] == 3) {

        if (isset($jsonBody['group_id']) && !isset($jsonBody['send_id'])) {

          $user = $this->GetUsersGroup($conn, $jsonBody['group_id']);

          if ($user['error']) {
            return;
          }

          $data = array(
            'group_id' => $jsonBody['group_id'],
            'user_id' => $user['data']
          );

          $this->sendNotify($conn, $data);
          return;
        }

        if (!isset($response['group_id']) && isset($jsonBody['send_id'])) {
          $this->sendNotify($conn, $jsonBody['send_id']);
          return;
        }

        return;
      }










      //modificado por Lucas Bethuel :(
      if ($jsonBody['type'] == 4) {

        $response = $this->GetNotif($jsonBody['id_user'], $jsonBody['id_checklist']);

        if ($response['error']) {
          return;
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










  //modificado por Lucas Bethuel :(


  private function GetNotif($id, $conn)
  {

    $daoMessage = new DaoMessage();
    $response  = $daoMessage->SelectSocketNotif($id);


    if ($response['error']) {
      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $response['message']
      )));
    }

    foreach ($this->connections as $connection) {

      if ($response['data'][0]->id_user_create == $this->connections[$connection]['id']) {

        $complete_object = (string)json_encode(array(
          "error" => false,
          "id_user" => 'id',
          "id_checklist" => 'id_checklist',
          "objectType" => '//'
        ));
        $connection->send($complete_object);
      }
    }
  }














  private function GetUsersGroup($conn, $id): array
  {

    $daoGroup = new DaoGroup();
    $response  = $daoGroup->SelectUser($id);


    if ($response['error']) {
      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $response['message']
      )));
    }

    return $response;
  }



  //   private function NotifyConnectionState($data, $state){
  //     foreach ($this->connections as $connection) {
  //         $connection->send((string)json_encode(array(
  //             "error" => false,
  //             "send_user_id" => (int)$data['id'],
  //             "type" => -1,
  //             "state" => $state
  //         )));
  //     }
  // }




  private function SendMessages($conn, $user, $id)
  {
    try {
      $daoMessage = new DaoMessage();
      $response  = $daoMessage->SelectLast($id);
      
      // var_dump($response);

      if ($response['error']) {
        echo "----------------------" . PHP_EOL;
        echo $response['message'] . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        $conn->send((string)json_encode(array(
          "error" => true,
          "message" => $response['message'],
        )));

        return;
      }

      foreach ($this->connections as $connection) {

        // var_dump(($user));
        for ($i = 0; $i < count($user); $i++) {
          if ($user[$i] == $this->connections[$connection]['id']) {
            $send_user_id = (int)$this->connections[$conn]['id'];

            $complete_object = (string)json_encode(array(
              "error" => false,
              "user" => $this->connections[$connection]['id'],
              "send_user" => $send_user_id,
              "message" => $response['data'][0]->message,
              "type" => $response['data'][0]->type,
              "objectType" => 'message'
            ));
            $connection->send($complete_object);
            echo "----------------------" . PHP_EOL;
            echo "user" .$this->connections[$connection]['id']. PHP_EOL;
            echo "send_user" .$send_user_id. PHP_EOL;
            echo "message ". $response['data'][0]->message . PHP_EOL;
            echo "----------------------" . PHP_EOL;
          }
        }
        // return;
      }

      // if ($user == $this->connections[$connection]['id']) {
      //   $send_user_id = (int)$this->connections[$conn]['id'];

      //   $complete_object = json_encode(array(
      //     "error" => false,
      //     "user_id" => $this->connections[$connection]['id'],
      //     "send_user" => $send_user_id
      //   ));

      // $connection->send($complete_object);


      // }
      // }
    } catch (Exception $e) {
      echo "----------------------" . PHP_EOL;
      echo "Error (GetConnectedUsers): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;
    }
  }


  private function sendNotify($conn, $notify)
  {
    $daoMessage = new DaoMessage();
    if (isset($notify['group_id'])) {

      $response  = $daoMessage->SelectNotification($this->connections[$conn]['id'], NULL, $notify['group_id']);

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

      // var_dump($response);
      // for($i=0; $i<$response["data"]; ++$i){
      foreach ($response["data"] as $res) {
        if ($res->notification) {
          echo "----------------------" . PHP_EOL;
          echo "notification group id" . $notify['group_id'] . PHP_EOL;
          echo $res->notification . PHP_EOL;
          echo "----------------------" . PHP_EOL;
          $conn->send((string)json_encode(array(
            "error" => false,
            "notify" => $res->notification
          )));
          return;
        }
      }
      echo "----------------------" . PHP_EOL;
      echo "notification group id" . $notify['group_id'] . PHP_EOL;
      echo $res->notification . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array(
        "error" => true,
        "notify" => $res->notification
      )));

      return;
    }



    // $notification = $daoMessage->UpdateNotification($notify, $this->connections[$conn]['id']);


    // if ($notification['error']) {
    //   echo "----------------------" . PHP_EOL;
    //   echo $notification['message'] . PHP_EOL;
    //   echo "----------------------" . PHP_EOL;
    //   $conn->send((string)json_encode(array(
    //     "error" => true,
    //     "message" => $notification['message'],
    //     "Message" => "erro message"
    //   )));
    //   return;
    // }


    $response  = $daoMessage->SelectNotification($this->connections[$conn]['id'], $notify, NULL);

    if ($response['error']) {
      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array(
        "error" => true,
        "notify" => $response['message']
      )));
      return;
    }

    foreach ($this->connections as $connection) {


      // var_dump((int)$this->connections[$connection]['id']);
      if ((int)$notify == (int)$this->connections[$connection]['id']) {
        foreach ($response["data"] as $res) {
          if ($res->notification == "true") {
            echo "----------------------" . PHP_EOL;
            echo "notification user id" . $notify . PHP_EOL;
            echo $res->notification . PHP_EOL;
            echo "----------------------" . PHP_EOL;
            $connection->send((string)json_encode(array(
              "error" => false,
              "notify" => $res->notification,
              "user" => $this->connections[$conn]['id'],
              "objectType" => 'notification'
            )));
            return;
          }
        }
        echo "----------------------" . PHP_EOL;
        echo "notification user id" . $notify . PHP_EOL;
        echo $res->notification . PHP_EOL;
        echo "----------------------" . PHP_EOL;
        $connection->send((string)json_encode(array(
          "error" => false,
          "notify" => $res->notification,
          "user" => $this->connections[$conn]['id'],
          "objectType" => 'notification'
        )));

        return;
      }
    }
  }
}
