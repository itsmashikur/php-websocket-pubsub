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
    protected $redis;
    protected $predis;
    protected $host;
    protected $port;
    protected $channel;

    public function __construct($loop)
    {

        $this->host = '127.0.0.1';

        $this->port = 6379;

        $this->clients = new \SplObjectStorage();

        $this->redis = new Redis($loop);

        $this->subscribeAdmin();

        $this->subscribeClient();

    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})" . PHP_EOL;

        $res = [
            'action' =>  'connection',
            'message' => 'Connected to PubSub Socket Server!',
        ];

        $conn->send(json_encode($res));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");

        $message = json_decode($msg, true);

        $channel = $message['channel'];

        //resource id
        $redis->set($channel, $from->resourceId);

        echo $from->resourceId;
        
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

    public function subscribeAdmin()
    {
        $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");

        $channel = 'admin-*';

        $redis->psubscribe($channel)->then(function () use ($channel) {
            echo "Subscribed to channels matching '$channel'.\n";
        });
        
        $redis->on('pmessage', function ($pattern, $channel, $message) {
            echo "Received message '$message' from channel '$channel' matching pattern '$pattern'.\n";
        });

    }

    public function subscribeClient()
    {   
        $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");

        $channel = 'client-*';

        $redis->psubscribe($channel)->then(function () use ($channel) {
            echo "Subscribed to channels matching '$channel'.\n";
        });
        
        $redis->on('pmessage', function ($pattern, $channel, $message) {

            $message = json_decode($message, true);

            $destination = $message['destination'];

            $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");

           $redis->get($destination)->then(function ($redis, $destination) use ($message) {
               echo "Resource ID: " . $redis->get($destination) . "\n";
           });

        });
    }
}


$port = readline('Enter port: ');

$loop = EventLoopFactory::create();

$webSocket = new WebSocketServer($loop);

$socketServer = new SocketServer('0.0.0.0:' . $port, $loop);

$ioServer = new IoServer(new HttpServer(new WsServer($webSocket)), $socketServer, $loop);

$loop->run();