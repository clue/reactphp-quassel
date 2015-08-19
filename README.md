# clue/quassel-react [![Build Status](https://travis-ci.org/clue/php-quassel-react.svg?branch=master)](https://travis-ci.org/clue/php-quassel-react)

Streaming, event-driven access to your [Quassel IRC](http://quassel-irc.org/) core,
built on top of [React PHP](http://reactphp.org/).

This is a low-level networking library which can be used to communicate with your Quassel IRC core.

> Note: This project is in beta stage! Feel free to report any issues you encounter.

## Quickstart example

See also the [examples](examples).


## Usage

### Factory

The `Factory` is responsible for creating your [`Client`](#client) instance.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);
```

If you need custom DNS, proxy or TLS settings, you can explicitly pass a
custom instance of the [`ConnectorInterface`](https://github.com/reactphp/socket-client#connectorinterface):

```php
$factory = new Factory($loop, $connector);
```

#### createClient()

The `createClient($uri)` method can be used to create a new [`Client`](#client).
It helps with establishing a plain TCP/IP connection to your Quassel IRC core
and probing for the correct protocol to use.

```php
$factory->createClient('localhost')->then(
    function (Client $client) {
        // client connected
    },
    function (Exception $e) {
        // an error occured while trying to connect client
    }
);
```

The `$uri` parameter must be a valid URI which must contain a host part and can optionally contain a port.

### Client

The `Client` is responsible for exchanging messages with your Quassel IRC core
and emitting incoming messages.

#### Commands

The `Client` exposes several public methods which can be used to send outgoing commands to your Quassel IRC core:

```php
$client->sendClientInit()
$client->sendClientLogin($user, $password);

$client->sendHeartBeatRequest($time);
$client->sendHeartBeatReply($time);

$client->sendBufferRequestBacklog($bufferId, $maxAmount);
$client->sendBufferInput($bufferInfo, $input);

// many more…
```

Listing all available commands is out of scope here, please refer to the [class outline](src/Client.php).

#### Processing

Sending commands is async (non-blocking), so you can actually send multiple commands in parallel.
You can send multiple commands in parallel, pending commands will be pipelined automatically.

Quassel IRC has some *interesting* protocol semantics, which means that commands do not use request-response style.
*Some* commands will trigger a message to be sent in response, see [on()](#on) for more details.

#### on()

The `on($eventName, $eventHandler)` method can be used to register a new event handler.
Incoming events will be forwarded to registered event handler callbacks:

```php
$client->on('message', function ($data) {
    // process an incoming message (raw message array)
    var_dump($data);
});

$client->on('close', function () {
    // the connection to Quassel IRC just closed
});
```

#### close()

The `close()` method can be used to force-close the Quassel connection immediately.

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/quassel-react": "~0.2.0"
    }
}
```

## Tests

In order to run the tests, you need PHPUnit:

```bash
$ phpunit
```

The test suite contains both unit tests and functional integration tests.
The functional tests require access to a running Quassel core server instance
and will be skipped by default.

Note that the functional test suite contains tests that set up your Quassel core
(i.e. register your initial user if not already present). This test will be skipped
if your core is already set up.
You can use a [Docker container](https://github.com/clue/docker-quassel-core)
if you want to test this against a fresh Quassel core:

```
$ docker run -it --rm -p 4242:4242 clue/quassel-core -d
```

If you want to run the functional tests, you need to supply *your* Quassel login
details in environment variables like this:

```bash
$ QUASSEL_HOST=127.0.0.1 QUASSEL_USER=quassel QUASSEL_PASS=secret phpunit
```

## License

Released under the terms of the permissive MIT license.

This library took some inspiration from other existing tools and libraries.
As such, a huge shoutout to the authors of the following repositories!
 
* [Quassel](https://github.com/quassel/quassel)
* [QuasselDroid](https://github.com/sandsmark/QuasselDroid)
* [node-libquassel](https://github.com/magne4000/node-libquassel)
* [node-qtdatastream](https://github.com/magne4000/node-qtdatastream)
