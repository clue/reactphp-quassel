# clue/reactphp-quassel [![Build Status](https://travis-ci.org/clue/reactphp-quassel.svg?branch=master)](https://travis-ci.org/clue/reactphp-quassel)

Streaming, event-driven access to your [Quassel IRC](http://quassel-irc.org/) core,
built on top of [ReactPHP](https://reactphp.org/).

This is a lightweight and low-level networking library which can be used to
communicate with your Quassel IRC core.
It allows you to react to incoming events (such as an incoming message) and to
perform new requests (such as sending an outgoing reply message).
This can be used to build chatbots, export your channel backlog, list online
users, forward backend events as a message to a channel and much more.
Unlike conventional IRC chatbots, Quassel IRC allows re-using your existing
identity and sharing it with both a person and a chatbot, so that an outside
person has no idea about this and only sees a single contact.

* **Async execution of requests** -
  Send any number of requests to your Quassel IRC core in parallel (automatic pipeline) and
  process their responses as soon as results come in.
* **Event-driven core** -
  Register your event handler callbacks to react to incoming events, such as an incoming chat message event.
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](http://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Future or custom commands and events require little to no changes to be supported.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested against actual Quassel IRC cores in the wild

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Factory](#factory)
    * [createClient()](#createclient)
  * [Client](#client)
    * [Commands](#commands)
    * [Processing](#processing)
    * [on()](#on)
    * [close()](#close)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

The Quassel IRC protocol is not exactly trivial to explain and has some
*interesting* message semantics. As such, it's highly recommended to check out
the [examples](examples) to get started.

## Usage

### Factory

The `Factory` is responsible for creating your [`Client`](#client) instance.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);
```

If you need custom DNS, proxy or TLS settings, you can explicitly pass a
custom instance of the [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface):

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
        // client connected (and authenticated)
    },
    function (Exception $e) {
        // an error occured while trying to connect (or authenticate) client
    }
);
```

The `$uri` parameter must be a valid URI which must contain a host part and can
optionally be preceded by the `quassel://` URI scheme and may contain a port
if your Quassel IRC core is not using the default TCP/IP port `4242`:

```php
$factory->createClient('quassel://localhost:4242');
```

Quassel supports password-based authentication. If you want to create a "normal"
client connection, you're recommended to pass the authentication details as part
of the URI. You can pass the password `h@llo` URL-encoded (percent-encoded) as
part of the URI like this:

```php
$factory->createClient('quassel://user:h%40llo@localhost')->then(
    function (Client $client) {
        // client sucessfully connected and authenticated
        $client->on('data', function ($data) {
            // next message to follow would be "SessionInit"
        });
    }
);
```

Note that if you do not pass the authentication details as part of the URI, then
this method will resolve with a "bare" `Client` right after connecting without
sending any application messages. This can be useful if you need full control
over the message flow, see below for more details.

Quassel uses "heartbeat" messages as a keep-alive mechanism to check the
connection between Quassel core and Quassel client is still active. This project
will automatically respond to each incoming "ping" (heartbeat request) with an
appropriate "pong" (heartbeat response) message. If you do not want this and
want to handle incoming heartbeat request messages yourself, you may pass the
optional `?pong=0` parameter like this:

```php
$factory->createClient('quassel://localhost?pong=0');
```

This automatic "pong" mechanism allows the Quassel core to detect the connection
to the client is still active. However, it does not allow the client to detect
if the connection to the Quassel core is still active. Because of this, this
project will automatically send a "ping" (heartbeat request) message to the
Quassel core if it did not receive any messages for 60s by default. If no
message has been received after waiting for another period, the connection is
assumed to be dead and will be closed. You can pass the `?ping=120.0` parameter
to change this default interval. The Quassel core uses a configurable ping
interval of 30s by default and also sends all IRC network state changes to the
client, so this mechanism should only really kick in if the connection looks
dead. If you do not want this and want to handle outgoing heartbeat request
messages yourself, you may pass the optional `?ping=0` parameter like this:

```php
$factory->createClient('quassel://localhost?ping=0');
```

>   This method uses Quassel IRC's probing mechanism for the correct protocol to
    use (newer "datastream" protocol or original "legacy" protocol).
    Protocol handling will be abstracted away for you, so you don't have to
    worry about this (see also below for more details about protocol messages).
    Note that this project does not currently implement encryption and
    compression support.

### Client

The `Client` is responsible for exchanging messages with your Quassel IRC core
and emitting incoming messages.
It implements the [`DuplexStreamInterface`](https://github.com/reactphp/stream#duplexstreaminterface),
i.e. it is both a normal readable and writable stream instance.

#### Commands

The `Client` exposes several public methods which can be used to send outgoing commands to your Quassel IRC core:

```php
$client->writeClientInit()
$client->writeClientLogin($user, $password);

$client->writeHeartBeatRequest($time);
$client->writeHeartBeatReply($time);

$client->writeBufferRequestBacklog($bufferId, $messageIdFirst, $messageIdLast, $maxAmount, $additional);
$client->writeBufferRequestBacklogAll($messageIdFirst, $messageIdLast, $maxAmount, $additional);
$client->writeBufferInput($bufferInfo, $input);

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
$client->on('data', function ($data) {
    // process an incoming message (raw message array)
    var_dump($data);
});

$client->on('end', function () {
    // connection ended, client will close
});

$client->on('error', function (Exception $e) {
    // an error occured, client will close
});

$client->on('close', function () {
    // the connection to Quassel IRC just closed
});
```

The `data` event will be forwarded with the PHP representation of whatever the
remote Quassel IRC core sent to this client.
From a consumer perspective this looks very similar to a parsed JSON structure,
but this actually uses a binary wire format under the hood.
This library exposes this parsed structure as-is and does usually not change
anything about it.

There are only few noticable exceptions to this rule:

*   Incoming buffers/channels and chat messages use complex data models, so they
    are represented by `BufferInfo` and `Message` respectively. All other data
    types use plain structured data, so you can access it's array-based
    structure very similar to a JSON-like data structure.
*   The legacy protocol uses plain times for heartbeat messages while the newer
    datastream protocol uses `DateTime` objects.
    This library always converts this to `DateTime` for consistency reasons.
*   The legacy protocol uses excessive map structures for initial "Network"
    synchronization, while the newer datastream protocol users optimized list
    structures to avoid repeatedly sending the same keys.
    This library always exposes the legacy protocol format in the same way as
    the newer datastream protocol for consistency reasons.

This combined basically means that you should always get consistent `data`
events for both the legacy protocol and the newer datastream protocol.

#### close()

The `close()` method can be used to force-close the Quassel connection immediately.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/quassel-react:^0.6
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

Internally, it will use the `ext-mbstring` for converting between different
character encodings for message strings.
If this extension is missing, then this library will use a slighty slower Regex
work-around that should otherwise work equally well.
Installing `ext-mbstring` is highly recommended.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
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
