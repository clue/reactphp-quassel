<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;

require __DIR__ . '/../vendor/autoload.php';

$host = '127.0.0.1';
if (isset($argv[1])) { $host = $argv[1]; }

echo 'Server: ' . $host . PHP_EOL;

echo 'User name: ';
$user = trim(fgets(STDIN));

echo 'Password: ';
$pass = trim(fgets(STDIN));

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$uri = rawurlencode($user) . ':' . rawurlencode($pass) . '@' . $host;

$factory->createClient($uri)->then(function (Client $client) use ($loop) {
    var_dump('CONNECTED');

    $client->on('data', function ($message) use ($client, $loop) {
        // session initialized => initialize all networks and buffers
        if (isset($message['MsgType']) && $message['MsgType'] === 'SessionInit') {
            var_dump('session initialized');

            foreach ($message['SessionState']['NetworkIds'] as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);
            }

            foreach ($message['SessionState']['BufferInfos'] as $buffer) {
                if ($buffer['type'] === 2) { // type == 4 for user
                    var_dump('requesting IrcChannel for ' . $buffer['name']);
                    $client->writeInitRequest('IrcChannel', $buffer['network'] . '/' . $buffer['id']);
                }
            }

            // send heartbeat message every 30s to check dropped connection
            $timer = $loop->addPeriodicTimer(30.0, function () use ($client) {
                $client->writeHeartBeatRequest();
            });

            // stop heartbeat timer once connection closes
            $client->on('close', function () use ($loop, $timer) {
                $loop->cancelTimer($timer);
            });

            var_dump('initialization completed, now waiting for incoming messages (assuming core receives any)');

            return;
        }

        $type = null;
        if (is_array($message) && isset($message[0])) {
            $type = $message[0];
        }

        // reply to heartbeat messages to avoid timing out
        if ($type === Protocol::REQUEST_HEARTBEAT) {
            //var_dump('heartbeat', $message[1]);
            $client->writeHeartBeatReply($message[1]);

            return;
        }

        // ignore heartbeat reply messages to our heartbeat requests
        if ($type === Protocol::REQUEST_HEARTBEATREPLY) {
            return;
        }

        if ($type === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $data = $message[2];
            echo $data['timestamp']->format(\DateTime::ISO8601) . ' in ' . $data['bufferInfo']['name'] . ' by ' . explode('!', $data['sender'], 2)[0] . ': ' . $data['content'] . PHP_EOL;

            return;
        }

        if ($type === Protocol::REQUEST_SYNC) {
            // ignore sync messages
            return;
        }

        echo 'received unhandled: ' . json_encode($message) . PHP_EOL;
    });

    $client->on('error', 'printf');
    $client->on('close', function () {
        echo 'Connection closed' . PHP_EOL;
    });
})->then(null, function ($e) {
    echo $e;
});

$loop->run();
