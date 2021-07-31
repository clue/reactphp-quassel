<?php

use Clue\React\Quassel\Factory;
use Clue\React\Quassel\Client;
use Clue\React\Quassel\Io\Protocol;
use Clue\React\Quassel\Models\BufferInfo;
use Clue\React\Quassel\Models\Message;

require __DIR__ . '/../vendor/autoload.php';

$host = '127.0.0.1';
if (isset($argv[1])) { $host = $argv[1]; }

echo 'Server: ' . $host . PHP_EOL;

echo 'User name: ';
$user = trim(fgets(STDIN));

echo 'Password: ';
$pass = trim(fgets(STDIN));

$factory = new Factory();

$uri = rawurlencode($user) . ':' . rawurlencode($pass) . '@' . $host;

$factory->createClient($uri)->then(function (Client $client) {
    var_dump('CONNECTED');

    $nicks = array();

    $client->on('data', function ($message) use ($client, &$nicks) {
        // session initialized => initialize all networks
        if (isset($message->MsgType) && $message->MsgType === 'SessionInit') {
            var_dump('session initialized, now waiting for incoming messages');

            foreach ($message->SessionState->NetworkIds as $nid) {
                var_dump('requesting Network for ' . $nid . ', this may take a few seconds');
                $client->writeInitRequest("Network", $nid);
            }

            return;
        }

        // new network created => initialize network (deleting will disconnect first, see below)
        // [2,"2networkCreated(NetworkId)",7]
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '2networkCreated(NetworkId)') {
            var_dump('requesting Network for ' . $message[2] . ', this may take a few seconds');
            $client->writeInitRequest("Network", $message[2]);

            return;
        }

        // network information received, remember nick used on this network
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_INITDATA && $message[1] === 'Network') {
            if (isset($message[3]->myNick)) {
                $nicks[$message[2]] = $message[3]->myNick;

                echo 'Network ' . $message[2] .' nick: ' . $message[3]->myNick . PHP_EOL;
            } else {
                echo 'Network ' . $message[2] . ' nick unknown (disconnected)' . PHP_EOL;
            }

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

        // update our nickname when connecting/disconnecting network
        // [1,"Network","6","setMyNick",null]
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_SYNC && $message[1] === 'Network' && $message[3] === 'setMyNick') {
            $network = (int)$message[2];
            if (isset($message[4])) {
                $nicks[$network] = $message[4];
                echo 'Network ' . $network . ' nick: ' . $nicks[$network] . PHP_EOL;
            } else {
                unset($nicks[$network]);
                echo 'Network ' . $network . ' nick unknown (disconnected)' . PHP_EOL;
            }

            return;
        }

        // chat message received
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_RPCCALL && $message[1] === '2displayMsg(Message)') {
            $in = $message[2];
            assert($in instanceof Message);
            $reply = null;

            // we may be connected to multiple networks with different nicks
            // find correct nick for current network
            $nick = isset($nicks[$in->bufferInfo->networkId]) ? $nicks[$in->bufferInfo->networkId] : null;

            // received "nick: ping" in any buffer/channel
            if ($nick !== null && strtolower($in->contents) === ($nick . ': ping')) {
                $reply = explode('!', $in->sender)[0] . ': pong :-)';
            }

            // received "ping" in direct query buffer (user to user)
            if (strtolower($in->contents) === 'ping' && $in->bufferInfo->type === BufferInfo::TYPE_QUERY) {
                $reply = 'pong :-)';
            }

            if ($reply !== null) {
                $client->writeBufferInput($in->bufferInfo, $reply);

                echo date('Y-m-d H:i:s') . ' Replied to ' . $in->bufferInfo->name . '/' . explode('!', $in->sender)[0] . ': "' . $in->contents . '"' . PHP_EOL;
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
