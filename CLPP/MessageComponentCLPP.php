<?php

require_once('../../DAO/CLPP/Group.php');
require_once('../../DAO/CLPP/Message.php');
require_once('../../DAO/CCPP/User.php');
require_once 'vendor/autoload.php';

class MessageComponentCLPP
{

  //modificado por Lucas Bethuel :(
  public function GetNotif($id, $conn)
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

  public function GetUsersGroup($conn, $id): array
  {

    $daoGroup = new DaoGroup();
    $response  = $daoGroup->SelectUser($id);


    if ($response['error']) {
  
      return(array(
        "error" => true,
        "message" => $response['message']
      ));
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

  public function SendMessages($conn, $user, $id)
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
    
      }

    } catch (Exception $e) {
      echo "----------------------" . PHP_EOL;
      echo "Error (GetConnectedUsers): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;
    }
  }

  public function sendNotify($conn, $notify)
  {
    $daoMessage = new DaoMessage();
    if (isset($notify['group_id'])) {

      $response  = $daoMessage->SelectNotification($this->connections[$conn]['id'], NULL, $notify['group_id']);

      if ($response['error']) {
          $data= array(
          "error" => true,
          "message" => $response['message'],
          "Notify" => "erro notification"
        );
        return $data;
      }

      foreach ($response["data"] as $res) {
        if ($res->notification) {
           return(array(
            "error" => false,
            "notify" => $res->notification,
            "user" => $this->connections[$conn]['id'],
            "objectType" => 'notification'
          ));
        }
      }
    
      return(array(
        "error" => false,
        "notify" => $res->notification,
        "user" => $this->connections[$conn]['id'],
        "objectType" => 'notification'
      ));

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
