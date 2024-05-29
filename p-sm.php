<?php

/**** Pattern Subscribe & Message ****/

require 'vendor/autoload.php';

use Clue\React\Redis\Factory;
use React\EventLoop\Factory as LoopFactory;


$loop = LoopFactory::create();

$redisFactory = new Factory($loop);


$redis = $redisFactory->createLazyClient('redis://127.0.0.1:6379');


$channel = 'client-*';

$redis->psubscribe($channel)->then(function () use ($channel) {
    echo "Subscribed to channels matching '$channel'.\n";
});

$redis->on('pmessage', function ($pattern, $channel, $message) {
    echo "Received message '$message' from channel '$channel' matching pattern '$pattern'.\n";
});

$loop->run();

?>
