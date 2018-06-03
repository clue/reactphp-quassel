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

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$uri = rawurlencode($user) . ':' . rawurlencode($pass) . '@' . $host;

$factory->createClient($uri)->then(function (Client $client) {
    var_dump('CONNECTED');

    $nicks = array();

    $client->on('data', function ($message) use ($client, &$nicks) {
        // session initialized => initialize all networks
        if (isset($message['MsgType']) && $message['MsgType'] === 'SessionInit') {
            var_dump('session initialized, now waiting for incoming messages');

            foreach ($message['SessionState']['NetworkIds'] as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);
            }

            return;
        }

        // network information received, remember nick used on this network
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_INITDATA && $message[1] === 'Network') {
            $nicks[$message[2]] = $message[3]['myNick'];

            echo 'Network ' . $message[2] .' nick: ' . $message[3]['myNick'] . PHP_EOL;

            return;
        }

        // update our nickname when renamed
        // [2,"__objectRenamed__","IrcUser","1/NEW","1/OLD"]
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '__objectRenamed__' && $message[2] === 'IrcUser') {
            $network = (int)$message[3];
            if (isset($nicks[$network]) && $nicks[$network] === explode('/', $message[4])[1]) {
                $nicks[$network] = explode('/', $message[3])[1];

                echo 'Network ' . $network . ' nick: ' . $nicks[$network] . PHP_EOL;
            }

            return;
        }

        // chat message received
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $data = $message[2];
            assert($data instanceof MessageModel);
            $reply = null;

            // we may be connected to multiple networks with different nicks
            // find correct nick for current network
            $nick = isset($nicks[$data->getBufferInfo()->getNetworkId()]) ? $nicks[$data->getBufferInfo()->getNetworkId()] : null;

            // received "nick: ping" in any buffer/channel
            if ($nick !== null && strtolower($data->getContents()) === ($nick . ': ping')) {
                $reply = explode('!', $data->getSender())[0] . ': pong :-)';
            }

            // received "ping" in direct query buffer (user to user)
            if (strtolower($data->getContents()) === 'ping' && $data->getBufferInfo()->getType() === 0x04) {
                $reply = 'pong :-)';
            }

            if ($reply !== null) {
                $client->writeBufferInput($data->getBufferInfo(), $reply);

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
