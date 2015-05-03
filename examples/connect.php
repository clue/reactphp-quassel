<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;

require __DIR__ . '/../vendor/autoload.php';

$debug = false;
$host = '127.0.0.1';
$user = array();
if (isset($argv[1])) { $host = $argv[1]; }

echo 'Server: ' . $host . PHP_EOL;

echo 'User name: ';
$user['name'] = trim(fgets(STDIN));

echo 'Password: ';
$user['password'] = trim(fgets(STDIN));

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient($host)->then(function (Client $client) use ($loop, $user, $debug) {
    var_dump('CONNECTED');

    if ($debug) {
        $client->on('message', function ($message) {
            var_dump($message);
        });
    }

    $client->on('message', function ($message) use ($client, $user) {
        $type = null;
        if (is_array($message) && isset($message['MsgType'])) {
            $type = $message['MsgType'];
        }

        if ($type === 'ClientInitAck') {
            if (!$message['Configured']) {
                var_dump('core not configured yet, you may want to issue a setup call manually');
                print_r($message['StorageBackends']);

                echo 'Hit enter to set-up server with defaults, otherwise cancel program now';
                fgets(STDIN);

                $client->sendCoreSetupData($user['name'], $user['password']);

                return;
            }
            var_dump('core ready, now logging in');
            $client->sendClientLogin($user['name'], $user['password']);

            return;
        }
        if ($type === 'CoreSetupAck') {
            var_dump('core set-up, now logging in');
            $client->sendClientLogin($user['name'], $user['password']);

            return;
        }
        if ($type === 'CoreSetupReject') {
            var_dump('Unable to set-up core', $message['Error']);

            return;
        }
        if ($type === 'ClientLoginReject') {
            var_dump('Unable to login', $message['Error']);

            var_dump('Now closing connection');
            $client->close();

            return;
        }
        if ($type === 'ClientLoginAck') {
            var_dump('successfully logged in, now waiting for a SessionInit message');

            return;
        }
        if ($type === 'SessionInit') {
            var_dump('session initialized');

            foreach ($message['SessionState']['NetworkIds'] as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->sendInitRequest("Network", $nid);
            }

            foreach ($message['SessionState']['BufferInfos'] as $buffer) {
                if ($buffer['type'] === 2) { // type == 4 for user
                    var_dump('requesting IrcChannel for ' . $buffer['name']);
                    $client->sendInitRequest('IrcChannel', $buffer['network'] . '/' . $buffer['id']);
                }
            }

            var_dump('initialization completed, now waiting for incoming messages (assuming core receives any)');

            return;
        }

        $type = null;
        if (is_array($message) && isset($message[0])) {
            $type = $message[0];
        }

        if ($type === Protocol::REQUEST_HEARTBEAT) {
            //var_dump('heartbeat', $message[1]);
            $client->sendHeartBeatReply($message[1]);

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

    $client->sendClientInit();

    $client->on('close', function () use (&$timer) {
        var_dump('CLOSED');
        $timer->cancel();
    });

    $timer = $loop->addTimer(60.0 * 60, function ($timer) use ($client) {
        var_dump('connection expired after ' . $timer->getInterval() . ' seconds');
        $client->close();
    });
})->then(null, function ($e) {
    echo $e;
});

$loop->run();
