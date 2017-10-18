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

$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient($host)->then(function (Client $client) use ($loop, $user) {
    var_dump('CONNECTED');

    $nicks = array();

    $client->on('data', function ($message) use ($client, $user, &$nicks, $loop) {
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

            foreach ($message['SessionState']['NetworkIds'] as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);
            }

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
            $reply = null;

            // we may be connected to multiple networks with different nicks
            // find correct nick for current network
            $nick = isset($nicks[$data['bufferInfo']['network']]) ? $nicks[$data['bufferInfo']['network']] : null;

            // received "nick: ping" in any buffer/channel
            if ($nick !== null && strtolower($data['content']) === ($nick . ': ping')) {
                $reply = explode('!', $data['sender'], 2)[0] . ': pong :-)';
            }

            // received "ping" in direct query buffer (user to user)
            if (strtolower($data['content']) === 'ping' && $data['bufferInfo']['type'] === 0x04) {
                $reply = 'pong :-)';
            }

            if ($reply !== null) {
                $client->writeBufferInput($data['bufferInfo'], $reply);

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
