<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as EventLoopFactory;
use Clue\React\Redis\Factory as Redis;
use React\EventLoop\Loop;
use React\Socket\Server as SocketServer;

require 'vendor/autoload.php';

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $host;
    protected $port;
    protected $redis;
    protected $channels;


    public function __construct($loop)
    {

        $this->host = '127.0.0.1';

        $this->port = 6379;

        $this->clients = new \SplObjectStorage();

        $this->redis = new Redis($loop);

        $this->subscribe('client-*');

        $this->subscribe('admin-*');

    }

    private function redis(){
        return $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})" . PHP_EOL;

        $res = [
            'action' =>  'connection',
            'message' => 'Connected to PubSub Socket Server! with resource ID: '. $conn->resourceId
        ];

        $conn->send(json_encode($res));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $redis = $this->redis();

        $message = json_decode($msg, true);

        $channel = $message['channel'];

        echo "Resource ID of Channel $channel set to : ". $from->resourceId ."\n";

        $redis->set($channel, $from->resourceId);
        
        if ($message['action'] == 'publish' && !empty($message['destination'])) {
            
            $destination = $message['destination'];

            $redis->publish($destination, $msg);

        }elseif($message['action'] == 'publish' && empty($message['destination'])){

            $redis->publish('admin-*', $msg);

        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected" . PHP_EOL;
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}" . PHP_EOL;
        $conn->close();
    }

    public function subscribe($channelPattern)
    {
        $redis = $this->redis();

        $redis->psubscribe($channelPattern)->then(function () use ($channelPattern) {
            echo "Subscribed to channels matching '$channelPattern'.\n";
        });

        $redis->on('pmessage', function ($pattern, $channel, $msg) {

            $message = json_decode($msg, true);
 
            $destination = $message['destination'];

            foreach($this->clients as $client) {

                $redis = $this->redis();

                $redis->get($destination)->then(function (string $resourceId) use ($client, $msg, $destination) {
                    if ($client->resourceId == $resourceId) {
                        echo "Resource ID {$resourceId} found of : ". $destination."\n";

                        $client->send($msg);
                    }
                });
            }
        });
    }

   
}


$loop = EventLoopFactory::create();

$webSocket = new WebSocketServer($loop);

$socketServer = new SocketServer('0.0.0.0:9090', $loop);

$ioServer = new IoServer(new HttpServer(new WsServer($webSocket)), $socketServer, $loop);

$loop->run();