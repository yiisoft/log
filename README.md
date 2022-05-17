<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii Logging Library</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/log/v/stable.png)](https://packagist.org/packages/yiisoft/log)
[![Total Downloads](https://poser.pugx.org/yiisoft/log/downloads.png)](https://github.com/yiisoft/log/actions?query=workflow%3Abuild)
[![Build status](https://github.com/yiisoft/log/workflows/build/badge.svg)](https://github.com/yiisoft/log/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/log/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/log/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/log/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/log/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Flog%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/log/master)
[![static analysis](https://github.com/yiisoft/log/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/log/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/log/coverage.svg)](https://shepherd.dev/github/yiisoft/log)

This package provides [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logging library. It is used in
[Yii Framework](http://www.yiiframework.com/) but is usable separately.

The logger sends passes messages to multiple targets. Each target may filter messages by their severity levels and categories and then export them to some medium such as file, email or syslog.

## Requirements

- PHP 8.0 or higher.

## Installation

The package could be installed with composer:

```shell
composer require yiisoft/log --prefer-dist
```

## General usage

Creating a logger:

```php

/**
 * List of class instances that extend the \Yiisoft\Log\Target abstract class.
 * 
 * @var \Yiisoft\Log\Target[] $targets
 */
$logger = new \Yiisoft\Log\Logger($targets);
```

Writing logs:

```php
$logger->emergency('Emergency message', ['key' => 'value']);
$logger->alert('Alert message', ['key' => 'value']);
$logger->critical('Critical message', ['key' => 'value']);
$logger->warning('Warning message', ['key' => 'value']);
$logger->notice('Notice message', ['key' => 'value']);
$logger->info('Info message', ['key' => 'value']);
$logger->debug('Debug message', ['key' => 'value']);
```

## Message Flushing and Exporting

Log messages are collected and stored in memory. To limit memory consumption, the logger will flush
the recorded messages to the log targets each time a certain number of log messages accumulate.
You can customize this number by calling the `\Yiisoft\Log\Logger::setFlushInterval()` method:

```php
$logger->setFlushInterval(100); // default is 1000
```

Each log target also collects and stores messages in memory.
Message exporting in a target follows the same principle as in the logger.
To change the number of stored messages, call the `\Yiisoft\Log\Target::setExportInterval()` method:

```php
$target->setExportInterval(100); // default is 1000
```

> Note: All message flushing and exporting also occurs when the application ends.

## Logging targets

This package contains two targets:

- `Yiisoft\Log\PsrTarget` - passes log messages to another [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger.
- `Yiisoft\Log\StreamTarget` - writes log messages to the specified output stream.

Extra logging targets are implemented as separate packages:

- [Database](https://github.com/yiisoft/log-target-db)
- [Email](https://github.com/yiisoft/log-target-email)
- [File](https://github.com/yiisoft/log-target-file)
- [Syslog](https://github.com/yiisoft/log-target-syslog)

See [Yii guide to logging](https://github.com/yiisoft/docs/blob/master/guide/en/runtime/logging.md) for more info.

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

The Yii Logging Library is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
