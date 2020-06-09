<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Logging Library</h1>
    <br>
</p>

This library provides [PSR-3] compatible logging library.
It is used in [Yii Framework] but is supposed to be usable separately.

[PSR-3]: https://www.php-fig.org/psr/psr-3/
[Yii Framework]: https://github.com/yiisoft/core

[![Latest Stable Version](https://poser.pugx.org/yiisoft/log/v/stable.png)](https://packagist.org/packages/yiisoft/log)
[![Total Downloads](https://poser.pugx.org/yiisoft/log/downloads.png)](https://github.com/yiisoft/log/actions?query=workflow%3Abuild)
[![Build status](https://github.com/yiisoft/log/workflows/build/badge.svg)](https://github.com/yiisoft/log/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/log/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/log/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/log/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/log/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Flog%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/log/master)
[![static analysis with phan](https://github.com/yiisoft/log/workflows/static%20analysis%20with%20phan/badge.svg)](https://github.com/yiisoft/log/actions?query=workflow%3A%22static+analysis+with+phan%22)

## Logging targets

Logging targets are implemented as separate packages:

- [Database](https://github.com/yiisoft/log-target-db)
- [Email](https://github.com/yiisoft/log-target-email)
- [File](https://github.com/yiisoft/log-target-file)
- [Syslog](https://github.com/yiisoft/log-target-syslog)
