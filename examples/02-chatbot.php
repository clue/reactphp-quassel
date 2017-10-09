<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;

require __DIR__ . '/../vendor/autoload.php';
$host = '127.0.0.1';
$user = array();
if (isset($argv[1])) { $host = $argv[1]; }

echo 'Server: ' . $host . PHP_EOL;

echo 'User name: ';
$user['name'] = trim(fgets(STDIN));

echo 'Password: ';
$user['password'] = trim(fgets(STDIN));

echo 'Keyword: ';
$user['keyword'] = trim(fgets(STDIN));

if (strlen($user['keyword']) < 3) {
    die('Keyword MUST contain at least 3 characters to avoid excessive spam');
}

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient($host)->then(function (Client $client) use ($loop, $user) {
    var_dump('CONNECTED');

    $client->on('data', function ($message) use ($client, $user, $loop) {
        $type = null;
        if (is_array($message) && isset($message['MsgType'])) {
            $type = $message['MsgType'];
        }

        if ($type === 'ClientInitAck') {
            if (!$message['Configured']) {
                var_dump('core not configured yet');
                $client->close();
                return;
            }
            var_dump('core ready, now logging in');
            $client->writeClientLogin($user['name'], $user['password']);

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
            var_dump('session initialized, now waiting for incoming messages');

            // send heartbeat message every 30s to check dropped connection
            $loop->addPeriodicTimer(30.0, function () use ($client) {
                $client->writeHeartBeatRequest(new \DateTime());
            });

            return;
        }

        // reply to heartbeat messages to avoid timing out
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_HEARTBEAT) {
            $client->writeHeartBeatReply($message[1]);

            return;
        }

        // chat message received
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $data = $message[2];

            if (strpos($data['content'], $user['keyword']) !== false) {
                $client->writeBufferInput($data['bufferInfo'], 'Hello from clue/quassel-react :-)');

                echo date('Y-m-d H:i:s') . ' Replied to ' . $data['bufferInfo']['name'] . '/' . explode('!', $data['sender'], 2)[0] . ': "' . $data['content'] . '"' . PHP_EOL;
            }
        }
    });

    $client->on('error', 'printf');
    $client->on('close', function () {
        echo 'Connection closed' . PHP_EOL;
    });

    $client->writeClientInit();
})->then(null, function ($e) {
    echo $e;
});

$loop->run();
