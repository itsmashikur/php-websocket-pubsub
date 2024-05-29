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
        $this->channel = 'messages';

        $this->host = '127.0.0.1';

        $this->port = 6379;

        $this->clients = new \SplObjectStorage();

        $this->redis = new Redis($loop);

        $this->channelSubscribe($loop);
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})" . PHP_EOL;
        //send message to client
        $conn->send('Hello from server!');
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        //on message send to redis
        $this->channelPublish($msg);
        echo "Message {$from->resourceId} says: {$msg}" . PHP_EOL;
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

    public function channelSubscribe($loop)
    {
        $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");

        $redis->subscribe($this->channel)->then(
            function () {

                echo "Subscribed to channel : {$this->channel}" . PHP_EOL;

            },
            function (Exception $e) use ($redis) {
                $redis->close();

                echo 'Unable to subscribe: ' . $e->getMessage() . PHP_EOL;
            },
        );

        $redis->on('message', function (string $channel, string $message) {

            echo 'Message on test' . $channel . ': ' . $message . PHP_EOL;

            foreach ($this->clients as $client) {
                $client->send($message);
            }
        });
    }

    public function channelPublish($msg)
    {
        $redis = $this->redis->createLazyClient("redis://{$this->host}:{$this->port}");
        $redis->publish($this->channel, $msg);
    }
}


$port = readline('Enter port: ');

$loop = EventLoopFactory::create();

$webSocket = new WebSocketServer($loop);

$socketServer = new SocketServer('0.0.0.0:' . $port, $loop);

$ioServer = new IoServer(new HttpServer(new WsServer($webSocket)), $socketServer, $loop);

$loop->run();