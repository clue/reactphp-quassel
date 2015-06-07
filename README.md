# clue/quassel-react [![Build Status](https://travis-ci.org/clue/php-quassel-react.svg?branch=master)](https://travis-ci.org/clue/php-quassel-react)

> Note: This project is in beta stage! Feel free to report any issues you encounter.

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
