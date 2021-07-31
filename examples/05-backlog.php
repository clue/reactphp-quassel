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

echo 'Channel to export (empty=all): ';
$channel = trim(fgets(STDIN));

$factory = new Factory();

$uri = rawurlencode($user) . ':' . rawurlencode($pass) . '@' . $host;

$factory->createClient($uri)->then(function (Client $client) use ($channel) {
    $client->on('data', function ($message) use ($client, $channel) {
        if (isset($message->MsgType) && $message->MsgType === 'SessionInit') {
            // session initialized => search channel ID for given channel name
            $id = null;
            foreach ($message->SessionState->BufferInfos as $buffer) {
                assert($buffer instanceof BufferInfo);
                $combined = $buffer->networkId . '/' . $buffer->name;
                if (($channel !== '' && $channel === $buffer->name) || $channel === (string)$buffer->id || $channel === $combined) {
                    $id = $buffer->id;
                }
            }

            // list all channels if channel could not be found
            if ($id === null && $channel !== '') {
                echo 'Error: Could not find the given channel, see full list: ' . PHP_EOL;
                var_dump($message->SessionState->BufferInfos);
                return $client->close();
            }

            // otherwise request backlog of last N messages
            if ($id === null) {
                $client->writeBufferRequestBacklogAll(-1, -1, 100, 0);
            } else {
                $client->writeBufferRequestBacklog($id, -1, -1, 100, 0);
            }
            return;
        }

        // print backlog and exit
        if (isset($message[0]) && $message[0] === Protocol::REQUEST_SYNC && $message[1] === 'BacklogManager') {
            // message for one buffer will be at index 9, for all buffers at index 8
            $messages = isset($message[9]) ? $message[9] : $message[8];

            foreach (array_reverse($messages) as $in) {
                assert($in instanceof Message);

                echo json_encode(
                    array(
                        'id' => $in->id,
                        'date' => $in->timestamp->format(\DATE_ATOM),
                        'channel' => $in->bufferInfo->name,
                        'sender' => explode('!', $in->sender)[0],
                        'contents' => $in->contents
                    ),
                    JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
                ) . PHP_EOL;
            }

            echo 'DONE (' . count($messages) . ' messages in backlog)' . PHP_EOL;
            $client->end();
            return;
        }

        // ignore initial CoreInfo reporting connected clients for Quassel v0.13+
        if (is_array($message) && isset($message[1]) && $message[1] === 'CoreInfo') {
            return;
        }

        echo 'received unexpected: ' . json_encode($message, JSON_PRETTY_PRINT) . PHP_EOL;
    });

    $client->on('error', 'printf');
    $client->on('close', function () {
        echo 'Connection closed' . PHP_EOL;
    });
})->then(null, function ($e) {
    echo $e;
});
