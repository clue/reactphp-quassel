# Changelog

## 0.6.0 (2017-10-20)

*   Feature / BC break: Upcast legacy Network sync model to newer datastream protocol variant
    and only support optional `quassel://` URI scheme and always use probing
    (#27 and #31 by @clue)

    This means that both the old "legacy" protocol and the newer "datastream"
    protocol now expose message data in the exact same format so that you no
    longer have to worry about protocol inconsitencies.

    > Note that this is not a BC break for most consumers, it merely updates
      the "legacy" fallback protocol to behave just like the the "datastream"
      protocol.

*   Feature / BC break: Significantly improve performance by updating QDataStream dependency and
    suggest `ext-mbstring` for character encoding and mark all protocol classes as `@internal` only
    (#26 by @clue)

    This update significantly improves performance and in particular parsing
    large messages (such as the `SessionInit` message) is now ~20 to ~100
    times as fast. What previously took seconds now takes mere milliseconds.
    This also makes the previously implicit dependency on `ext-mbstring`
    entirely optional.
    It is recommended to install this extension to support special characters
    outside of ASCII / ISO8859-1 range.

    > Note that this is not a BC break for most consumers, it merely updates
      internal protocol handler classes.

*   Feature / Fix: Report error if connection ends while receiving data and
    simplify close logic to remove all event listeners once closed
    (#30 by @clue)

*   Feature: Automatically send current timestamp for heartbeat requests by default unless explicit timestamp is given
    (#32 by @clue)

    ```php
    // new: no parameter sends current timestamp
    $client->writeHeartBeatRequest();
    ```

*   Feature: Limit incoming packet size to 16 MB to avoid excessive buffers
    (#29 by @clue)

*   Update examples and add chatbot examples
    (#28 by @clue)

## 0.5.0 (2017-08-05)

*   Feature / BC break: Replace legacy SocketClient with new Socket component and
    improve forward compatibility with new components
    (#25 by @clue)

    > Note that this is not a BC break for most consumers, it merely updates
      internal references and the optional parameter passed to the `Factory`.

    ```php
    // old from SocketClient component
    $dnsFactory = new React\Dns\Resolver\Factory();
    $resolver = $dnsFactory->create('8.8.8.8', $loop);
    $connector = new React\SocketClient\Connector($loop, $resolver);
    $factory = new Factory($loop, $connector);

    // new from Socket component
    $connector = new React\Socket\Connector($loop, array(
        'dns' => '8.8.8.8'
    ));
    $factory = new Factory($loop, $connector);
    ```

*   Forward compatibility with PHP 7.1 and PHPUnit v5
    (#24 by @clue)

*   Improve test suite by locking Travis distro so new defaults will not break the build
    (#23 by @clue)

## 0.4.0 (2016-09-26)

*   Feature / BC break: The Client implements DuplexStreamInterface and behaves like a normal stream
    (#21 and #22 by @clue)

    ```php
    // old (custom "message" event)
    $client->on('message', $callback);
    
    // new (default "data" event)
    $client->on('data', $callback);
    
    // old (applies to app send*() methods
    $client->sendClientInit(…);
    
    // new (now uses write*() prefix)
    $client->writeClientInit(…);
    
    // shared interfaces allow for interoperability with other components
    $client->pipe($logger);
    
    // allows advanced / custom messages through writable interface
    $client->write(array(…));
    
    // supports and reports back pressure to avoid buffer overflows
    $more = $client->write*(…);
    $client->pause();
    $client->resume();
    ```

*   Feature: Use default time zone and support sub-second accuracy for heartbeats
    (#20 by @clue)

## 0.3.1 (2016-09-24)

*   Feature: Support SocketClient v0.5 (while keeping BC)
    (#18 by @clue)

*   Maintenance: First class support for PHP 5.3 through PHP 7 and HHVM
    (#19 by @clue)

## 0.3.0 (2015-08-20)

*   Feature: Support newer "datastream" protocol
    ([#13](https://github.com/clue/php-quassel-react/pull/13), [#15](https://github.com/clue/php-quassel-react/pull/15))

*   Feature: Explicitly pass "legacy" protocol scheme to avoid probing protocol
    ([#16](https://github.com/clue/php-quassel-react/pull/16))

*   Improved documentation, more SOLID code base and updated dependencies.
    ([#11](https://github.com/clue/php-quassel-react/pull/11), [#14](https://github.com/clue/php-quassel-react/pull/14))

## 0.2.1 (2015-05-14)

*   Update clue/qdatastream to v0.5.0
    ([#12](https://github.com/clue/php-quassel-react/pull/12))

## 0.2.0 (2015-05-12)

*   Feature: Support sending buffer input, accessing its backlog and (dis)connecting networks
    ([#9](https://github.com/clue/php-quassel-react/pull/9))

*   Feature: Support sending heart beat requests and fix invalid heart beat timezone
    ([#10](https://github.com/clue/php-quassel-react/pull/10))

## 0.1.0 (2015-05-03)

*   First tagged release
