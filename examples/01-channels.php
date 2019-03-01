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

$factory->createClient($uri)->then(function (Client $client) {
    var_dump('CONNECTED');

    $await = array();

    $client->on('data', function ($message) use ($client, &$await) {
        // session initialized => initialize all networks
        if (isset($message->MsgType) && $message->MsgType === 'SessionInit') {
            var_dump('session initialized');

            foreach ($message->SessionState->NetworkIds as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);

                $await[$nid] = true;
            }

            if ($await) {
                var_dump('initialization completed, now waiting for network information');
            } else {
                var_dump('no networks found');
                $client->close();
            }

            return;
        }

        // network information received
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_INITDATA && $message[1] === 'Network') {
            // print network information except for huge users/channels list
            $info = clone $message[3];
            unset($info->IrcUsersAndChannels);
            echo json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;

            // print names of all known channels on this network (if connected)
            if (isset($message[3]->IrcUsersAndChannels->Channels)) {
                foreach ($message[3]->IrcUsersAndChannels->Channels->name as $name) {
                    echo $name . PHP_EOL;
                }
            } else {
                echo 'No channels in ' . $message[3]->networkName . ' (disconnected)' . PHP_EOL;
            }

            // close connection after showing all networks
            $id = $message[2];
            unset($await[$id]);
            if (!$await) {
                var_dump('network information received');
                $client->close();
            }
            return;
        }

        // ignore initial CoreInfo reporting connected clients for Quassel v0.13+
        if (is_array($message) && isset($message[1]) && $message[1] === 'CoreInfo') {
            return;
        }

        echo 'received unhandled: ' . json_encode($message, JSON_PRETTY_PRINT) . PHP_EOL;
    });

    $client->on('error', 'printf');
    $client->on('close', function () {
        echo 'Connection closed' . PHP_EOL;
    });
})->then(null, function ($e) {
    echo $e;
});

$loop->run();
