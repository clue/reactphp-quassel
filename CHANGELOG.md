# Changelog

## 0.4.0 (2016-09-26)

*   Feature / BC break: The Client implement DuplexStreamInterface and behaves like a normal stream
    (#21, #22 by @clue)

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
