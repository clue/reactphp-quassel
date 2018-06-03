<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Models\Message;
use Clue\React\Quassel\Models\BufferInfo;

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

$factory->createClient($uri)->then(function (Client $client) {
    var_dump('CONNECTED');

    $client->on('data', function ($message) use ($client) {
        // session initialized => initialize all networks and buffers
        if (isset($message['MsgType']) && $message['MsgType'] === 'SessionInit') {
            var_dump('session initialized');

            foreach ($message['SessionState']['NetworkIds'] as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);
            }

            foreach ($message['SessionState']['BufferInfos'] as $buffer) {
                assert($buffer instanceof BufferInfo);
                if ($buffer->getType() === BufferInfo::TYPE_CHANNEL) {
                    var_dump('requesting IrcChannel for ' . $buffer->getName());
                    $client->writeInitRequest('IrcChannel', $buffer->getNetworkId() . '/' . $buffer->getId());
                }
            }

            var_dump('initialization completed, now waiting for incoming messages (assuming core receives any)');

            return;
        }

        $type = null;
        if (is_array($message) && isset($message[0])) {
            $type = $message[0];
        }

        // ignore heartbeat requests and reply messages to our heartbeat requests
        if ($type === Protocol::REQUEST_HEARTBEAT || $type === Protocol::REQUEST_HEARTBEATREPLY) {
            return;
        }

        if ($type === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $in = $message[2];
            assert($in instanceof Message);
            echo date(DATE_ISO8601, $in->getTimestamp()) . ' in ' . $in->getBufferInfo()->getName() . ' by ' . explode('!', $in->getSender())[0] . ': ' . $in->getContents() . PHP_EOL;

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
