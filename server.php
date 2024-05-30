<?php

require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop as EventLoop;
use React\Socket\SocketServer;
use Clue\React\Redis\RedisClient;

class PubSubServer implements MessageComponentInterface
{
    protected $clients;
    protected $hostPort;
    protected $data;
    protected $redis;

    public function __construct($hostPort, $loop)
    {
        $this->clients = new \SplObjectStorage();

        $this->hostPort = $hostPort;

        $this->data = [];

        $this->redis = new RedisClient($this->hostPort);

        $this->patternSubscribe('client-*');

        $this->patternSubscribe('admin-*');
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})" . PHP_EOL;

        $res = [
            'channel' => 'WebSocket Server',
            'action' => 'connection',
            'message' => 'Connected to PubSub Socket Server!',
        ];

        $conn->send(json_encode($res));
    }

    public function onMessage(ConnectionInterface $from, $msgJson)
    {
        $msgArray = json_decode($msgJson, true);

        //set a item in data array with sender's channel as key & resource id as value
        $this->setData($msgArray['channel'], $from->resourceId);

        //if message has key 'receiver' then send message to user else send to all admins
        if ($msgArray['action'] == 'publish' && !empty($msgArray['receiver'])) {
            //publish to user
            $this->redis->publish($msgArray['receiver'], $msgJson);

        } elseif ($msgArray['action'] == 'publish' && empty($msgArray['receiver'])) {
            
            //get all channels from data
            foreach ($this->data as $key => $value) {

                //publish to only admin's channels from data
                if (strpos($key, 'admin-') !== false) {

                    //there is no receiver of messages from users so we will set it to admins channel from this loop
                    $msgArray['receiver'] = $key;
                    //publish to admin
                    $this->redis->publish($key, json_encode($msgArray));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        $key = $this->getKey($conn->resourceId);

        $this->unsetData($key);

        echo "Connection {$key} has disconnected" . PHP_EOL;
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}" . PHP_EOL;
        $conn->close();
    }

    public function patternSubscribe($channelPattern)
    {

        $redis = new RedisClient($this->hostPort);

        $redis->psubscribe($channelPattern);

        $redis->on('pmessage', function ($pattern, $channel, $msgJson) {

            $msgArray = json_decode($msgJson, true);

            //get all connected clients
            foreach ($this->clients as $client) {

                $receiverResourceId = $this->getData($msgArray['receiver']);

                $senderResourceId   = $this->getData($msgArray['channel']);

                //send sender's message to sender and receiver
                if ($client->resourceId == $senderResourceId || $client->resourceId == $receiverResourceId) {
                    
                    $client->send($msgJson);
                }
            }
        });
    }

    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function getData($key)
    {
        return $this->data[$key] ?: null;
    }

    public function unsetData($key)
    {
        unset($this->data[$key]);
    }

    public function getKey($value)
    {
        return array_search($value, $this->data, true);
    }
}

$pubsub = '127.0.0.1:6379';
$socket = '127.0.0.1:9090';

$loop = EventLoop::get();

$pubSubServer = new PubSubServer($pubsub, $loop);
$socketServer = new SocketServer($socket, [], $loop);

$ioServer = new IoServer(
    new HttpServer(new WsServer($pubSubServer)), $socketServer, $loop
);

echo "WebSocket Socket Server has started on $socket" . PHP_EOL;

$loop->run();