<?php

use Model\CCPP\User;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\MessageComponentInterface;

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

class MessageComponent implements MessageComponentInterface{
    //private SplObjectStorage $connections;

    public function __construct()
    {
        system("clear");
        echo "----------------------" . PHP_EOL;
        echo "Initializing server..." . PHP_EOL;
        try{
            $this->connections = new SplObjectStorage();
        }catch (Exception $e){
            echo "Error (__construct): " . $e->getMessage();
        }
        echo "----------------------" . PHP_EOL;
    }

    function onOpen(ConnectionInterface $conn)
    {
        echo "----------------------" . PHP_EOL;
        echo "New connection..." . PHP_EOL;
        try{
            $this->connections->attach($conn);
            $count = $this->connections->count();
            echo "Count: " . $count . PHP_EOL;
        }catch (Exception $e){
            echo "Error (onOpen): " . $e->getMessage();
        }
        echo "----------------------" . PHP_EOL;
    }

    function onClose(ConnectionInterface $conn)
    {
        echo "----------------------" . PHP_EOL;
        echo "End connection..." . PHP_EOL;
        try{
            $data = array(
                "id" => $this->connections[$conn]['id'],
                "user" => $this->connections[$conn]['user']
            );

            echo "User: " . $this->connections[$conn]['user'] . PHP_EOL;
            
            $this->NotifyConnectionState($data, "disconnected");

        }catch (Exception $e){
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

            if($msg == "__ping__"){
                $conn->send((string)"__pong__");
                return;
            }

            $jsonBody = json_decode($msg, true);

            if ($this->connections[$conn] == null) {
                $this->SetConnection($conn, $jsonBody);
                return;
            }

            $type = $jsonBody['type'];

            if($type === -2){
                $this->GetConnectedUsers($conn);
                return;
            }

            if($type === -3){
                $this->SendMessageToUser($conn, $jsonBody);
                return;
            }

            if(!isset($jsonBody['object']) || !isset($jsonBody['task_id']) || !isset($jsonBody['user_id'])){
                echo "----------------------" . PHP_EOL;
                echo "(object, task_id, user_id) is broken" . PHP_EOL;
                echo "----------------------" . PHP_EOL;
                $conn->send((string)json_encode(array("error" => true, "message" => "(object, task_id, user_id) is broken")));
                return;
            }

            $object = $jsonBody['object'];
            $task_id = $jsonBody['task_id'];

            $response = $this->GetTaskUsers($conn, $task_id);
            if($response['error']){
                echo "Error: " . $response['message'].PHP_EOL;
                return;
            }

            $this->SendMessages($conn, $response, $object, $task_id, $type);

        }catch (Exception $e){       
            
            echo "----------------------" . PHP_EOL;
            echo "Error (onMessage): " . $e->getMessage() . PHP_EOL;
            echo "----------------------" . PHP_EOL;

            $conn->send((string)json_encode(array(
                "error" => true,
                "message" => $e->getMessage()
            )));
        }
    }

    private function SetConnection($conn, $jsonBody)
    {
        if(!isset($jsonBody['auth']) || !isset($jsonBody['app_id'])){
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
            if($response['message'] == "No data") {

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
        foreach ($this->connections as $connection){
            if ($this->connections[$connection]['id'] == $response["data"][0]->id){
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
        $this->NotifyConnectionState($data, "connected");

        echo "----------------------" . PHP_EOL;
        echo "New connection for " . $data['user'] . PHP_EOL;
        echo "----------------------" . PHP_EOL;
    }

    //Get connected users by task_id
    private function GetTaskUsers($conn, $task_id): array
    {
        echo "----------------------" . PHP_EOL;
        echo "TaskID: " . $task_id . PHP_EOL;
        echo "UserID: " . $this->connections[$conn]['id'] . PHP_EOL;
        echo "User: " . $this->connections[$conn]['user'] . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        $daoTask_User = new DAOTask_User();
        $response = $daoTask_User->SelectAllUserID($task_id);

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

    private function SendMessages($conn, $response, $object, $task_id, $type)
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

    private function NotifyConnectionState($data, $state){
        foreach ($this->connections as $connection) {
            $connection->send((string)json_encode(array(
                "error" => false,
                "send_user_id" => (int)$data['id'],
                "type" => -1,
                "state" => $state
            )));
        }
    }

    private function GetConnectedUsers($conn)
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

    private function getID($id, $list): bool
    {
        for ($j = 0; $j < count($list); $j++){
            if($id === $list[$j]){
                return true;
            }
        }
        return false;
    }

    //Send message to specific connection
    private function SendMessageToUser($conn, $jsonBody)
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