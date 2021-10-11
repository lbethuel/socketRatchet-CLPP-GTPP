<?php

require_once('../DAO/CCPP/User.php');

require_once('../DAO/GTPP/Task_User.php');
require_once('../DAO/GTPP/Message.php');
require_once('../DAO/GTPP/Notify.php');
require_once('../Model/GTPP/Message.php');
require_once('../Model/GTPP/Notify.php');

require_once('../Utils/UTF8.php');

require_once 'vendor/autoload.php';

//$type is a variable to set specific configuration
//Type -1 connection status (login/logout)
//Type -2 all connection status (list of connected users)
//Type -3 message to a specific connection

class MessageComponentGTPP
{

    public function SendMessages($conn, $response, $object, $task_id, $type)
    {
        try{

            $list = array();  
            foreach ($this->connections as $connection) {
                //if ($connection !== $conn) {
 
                for ($i = 0; $i < count($response['data']); $i++) {

                    //echo "Response:".$response['data'][$i]->user_id.PHP_EOL;
                    //echo "connections:".$this->connections[$connection]['user'].PHP_EOL;

                    if ($response['data'][$i]->user_id === $this->connections[$connection]['id']) {
                        $send_user_id = (int)$this->connections[$conn]['id'];

                        /*if(!$this->ConnectionVerify($connection)){
                            return;
                        }*/

                        $complete_object = json_encode(array(
                            "error" => false,
                            "user_id" => (int)$this->connections[$connection]['id'],
                            "send_user_id" => (int)$send_user_id,
                            "object" => $object,
                            "task_id" => $task_id,
                            "type"=>$type,
                        ));

                        //echo $complete_object;

                        $connection->send($complete_object);
                        array_push($list, (int)$response['data'][$i]->user_id);
                    }
                }
            }
            
            $object = json_encode($object);
            $daoNotify = new DAONotify();
            $array = $response['data'];

            for ($i = 0; $i < count($array); $i++){
                if(!$this->getID((int)$array[$i]->user_id,$list)){

                    $user_id = $array[$i]->user_id;

                    $notify = new Notify(
                        null,
                        $user_id,
                        $this->connections[$conn]['id'],
                        $task_id,
                        $type,
                        $object
                    );
            
                    $response = $daoNotify->Insert($notify);
            
                    if($response['error']){
                        echo "----------------------" . PHP_EOL;
                        echo $response['message']. PHP_EOL;
                        echo "----------------------" . PHP_EOL;
                        return;
                    }
                }
            }

        }catch (Exception $e){
            echo "----------------------" . PHP_EOL;
            echo "Error (SendMessages): " . $e->getMessage() . PHP_EOL;
            echo "----------------------" . PHP_EOL;
        }
    }

    public function NotifyConnectionState($data, $state){
        foreach ($this->connections as $connection) {
            $connection->send((string)json_encode(array(
                "error" => false,
                "send_user_id" => (int)$data['id'],
                "type" => -1,
                "state" => $state
            )));
        }
    }

    public function GetConnectedUsers($conn)
    {
        try{
            $userList = array();

            foreach ($this->connections as $connection) {
                $user_id = (int)$this->connections[$connection]['id'];

                array_push($userList, (int)$user_id);
            }

            $conn->send((string)json_encode(array(
                "error" => false,
                "user" => $userList,
                "type" => -2
            )));
        }catch(Exception $e){
            echo "----------------------" . PHP_EOL;
            echo "Error (GetConnectedUsers): " . $e->getMessage() . PHP_EOL;
            echo "----------------------" . PHP_EOL;
        }
    }

    public function getID($id, $list): bool
    {
        for ($j = 0; $j < count($list); $j++){
            if($id === $list[$j]){
                return true;
            }
        }
        return false;
    }

    //Send message to specific connection
    public function SendMessageToUser($conn, $jsonBody)
    {
        foreach ($this->connections as $connection) {

            if ($jsonBody['user_id'] === $this->connections[$connection]['id']) {
                $send_user_id = (int)$this->connections[$conn]['id'];

                $complete_object = json_encode(array(
                    "error" => false,
                    "user_id" => $jsonBody['user_id'],
                    "send_user_id" => (int)$send_user_id,
                    "object" => $jsonBody['object'],
                    "task_id" => $jsonBody['task_id'],
                    "type"=>$jsonBody['type'],
                ));

                $connection->send($complete_object);
            }
        }
    }

}