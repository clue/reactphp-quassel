<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Models\MessageModel;

require __DIR__ . '/../vendor/autoload.php';

$host = '127.0.0.1';
if (isset($argv[1])) { $host = $argv[1]; }

echo 'Server: ' . $host . PHP_EOL;

echo 'User name: ';
$user = trim(fgets(STDIN));

echo 'Password: ';
$pass = trim(fgets(STDIN));

echo 'Keyword: ';
$keyword = trim(fgets(STDIN));

if (strlen($keyword) < 3) {
    die('Keyword MUST contain at least 3 characters to avoid excessive spam');
}

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$uri = rawurlencode($user) . ':' . rawurlencode($pass) . '@' . $host;

$factory->createClient($uri)->then(function (Client $client) use ($keyword) {
    var_dump('CONNECTED');

    $client->on('data', function ($message) use ($client, $keyword) {
        // session initialized
        if (isset($message['MsgType']) && $message['MsgType']=== 'SessionInit') {
            var_dump('session initialized, now waiting for incoming messages');

            return;
        }

        // chat message received
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $data = $message[2];
            assert($data instanceof MessageModel);

            if (strpos($data->getContents(), $keyword) !== false) {
                $client->writeBufferInput($data->getBufferInfo(), 'Hello from clue/quassel-react :-)');

                echo date('Y-m-d H:i:s') . ' Replied to ' . $data->getBufferInfo()->getName() . '/' . explode('!', $data->getSender())[0] . ': "' . $data->getContents() . '"' . PHP_EOL;
            }
        }
    });

    $client->on('error', 'printf');
    $client->on('close', function () {
        echo 'Connection closed' . PHP_EOL;
    });
})->then(null, function ($e) {
    echo $e;
});

$loop->run();
