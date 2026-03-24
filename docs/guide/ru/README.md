# Yii Logging Library

Пакет предоставляет [PSR-3](https://www.php-fig.org/psr/psr-3/)-совместимую библиотеку логирования.
Активно используется в [Yii Framework](https://www.yiiframework.com/), но может работать и как отдельный пакет.

Логгер отправляет сообщения в один или несколько таргетов. Каждый таргет может фильтровать сообщения
по уровню и категории, а затем экспортировать их в нужное хранилище - файл, email, syslog и т.д.

## Требования

- PHP 8.0 или выше.

## Установка

```shell
composer require yiisoft/log
```

## Использование

Создание логгера:

```php
/**
 * Список экземпляров классов, наследующих \Yiisoft\Log\Target.
 *
 * @var \Yiisoft\Log\Target[] $targets
 */
$logger = new \Yiisoft\Log\Logger($targets);
```

Запись логов:

```php
$logger->emergency('Emergency message', ['key' => 'value']);
$logger->alert('Alert message', ['key' => 'value']);
$logger->critical('Critical message', ['key' => 'value']);
$logger->warning('Warning message', ['key' => 'value']);
$logger->notice('Notice message', ['key' => 'value']);
$logger->info('Info message', ['key' => 'value']);
$logger->debug('Debug message', ['key' => 'value']);
```

### Плейсхолдеры PSR-3

Логгер совместим с [PSR-3](https://www.php-fig.org/psr/psr-3/) и поддерживает плейсхолдеры в сообщениях.
Плейсхолдеры в строке сообщения заменяются значениями из массива контекста:

```php
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john_doe',
    'ip' => '192.168.1.1',
]);
// Запишет: "User john_doe logged in from 192.168.1.1"
```

Имена плейсхолдеров заключаются в фигурные скобки `{placeholder}` и должны соответствовать ключам массива контекста.

#### Вложенные значения контекста

Как расширение спецификации PSR-3 логгер поддерживает доступ к вложенным значениям массива через точечную нотацию:

```php
$logger->info('User {user.name} with ID {user.id} performed action', [
    'user' => [
        'id' => 123,
        'name' => 'John Doe',
    ],
]);
// Запишет: "User John Doe with ID 123 performed action"
```

#### Поддерживаемые типы данных

В плейсхолдерах можно использовать разные типы данных:

- Строки и числа - выводятся как есть
- `null` - выводится как пустая строка
- Stringable-объекты - конвертируются через `__toString()`
- Массивы и объекты - форматируются через VarDumper

```php
$logger->warning('Failed to process order {order_id}', [
    'order_id' => 12345,
]);

$logger->error('Invalid data: {data}', [
    'data' => ['key' => 'value'],
]);
```

#### Сохранение данных контекста

Массив контекста сохраняется в сообщении и может использоваться таргетами для фильтрации,
форматирования и экспорта. Это позволяет передавать структурированные данные вместе с текстовым сообщением:

```php
$logger->info('Payment processed', [
    'amount' => 99.99,
    'currency' => 'USD',
    'transaction_id' => 'txn_123456',
    'user_id' => 42,
]);
```

### Сброс и экспорт сообщений

Лог-сообщения накапливаются в памяти. Чтобы ограничить потребление памяти, логгер сбрасывает
накопленные сообщения в таргеты при достижении определённого количества.
Это количество настраивается через `\Yiisoft\Log\Logger::setFlushInterval()`:

```php
$logger->setFlushInterval(100); // по умолчанию 1000
```

Каждый таргет тоже накапливает сообщения в памяти. Экспорт работает по тому же принципу.
Количество сообщений перед экспортом настраивается через `\Yiisoft\Log\Target::setExportInterval()`:

```php
$target->setExportInterval(100); // по умолчанию 1000
```

> Примечание: сброс и экспорт всех сообщений также происходит при завершении приложения.

### Таргеты логирования

В пакете есть два встроенных таргета:

- `Yiisoft\Log\PsrTarget` - передаёт сообщения в другой [PSR-3](https://www.php-fig.org/psr/psr-3/)-совместимый логгер.
- `Yiisoft\Log\StreamTarget` - записывает сообщения в указанный поток вывода.

Дополнительные таргеты реализованы в отдельных пакетах:

- [Database](https://github.com/yiisoft/log-target-db)
- [Email](https://github.com/yiisoft/log-target-email)
- [File](https://github.com/yiisoft/log-target-file)
- [Syslog](https://github.com/yiisoft/log-target-syslog)

### Провайдеры контекста

Провайдеры контекста добавляют дополнительные данные к каждому лог-сообщению. Провайдер передаётся
в конструктор `Logger`:

```php
$logger = new \Yiisoft\Log\Logger(contextProvider: $myContextProvider);
```

Доступны следующие провайдеры:

- `SystemContextProvider` - добавляет системную информацию (время, потребление памяти, трейс, категорию по умолчанию);
- `CommonContextProvider` - добавляет произвольные общие данные;
- `CompositeContextProvider` - объединяет несколько провайдеров в один.

По умолчанию логгер использует `SystemContextProvider`.

#### `SystemContextProvider`

`SystemContextProvider` добавляет в контекст:

- `time` - текущий Unix timestamp с микросекундами (float);
- `trace` - массив информации о стеке вызовов;
- `memory` - использование памяти в байтах;
- `category` - категория сообщения (по умолчанию "application").

Параметры конструктора `Yiisoft\Log\ContextProvider\SystemContextProvider`:

- `traceLevel` - сколько уровней стека вызовов логировать для каждого сообщения. Если больше 0,
  будет залогировано не более указанного количества вызовов. Учитываются только вызовы из кода приложения.
- `excludedTracePaths` - массив путей, исключаемых из трейса.

Пример:

```php
$logger = new \Yiisoft\Log\Logger(
    contextProvider: new Yiisoft\Log\ContextProvider\SystemContextProvider(
        traceLevel: 3,
        excludedTracePaths: [
            '/vendor/yiisoft/di',
        ],
    ),
);
```

#### `CommonContextProvider`

`CommonContextProvider` позволяет добавить произвольные данные в контекст каждого сообщения:

```php
$logger = new \Yiisoft\Log\Logger(
    contextProvider: new Yiisoft\Log\ContextProvider\CommonContextProvider([
       'environment' => 'production',
    ]),
);
```

#### `CompositeContextProvider`

`CompositeContextProvider` объединяет несколько провайдеров:

```php
$logger = new \Yiisoft\Log\Logger(
    contextProvider: new Yiisoft\Log\ContextProvider\CompositeContextProvider(
        new Yiisoft\Log\ContextProvider\SystemContextProvider(),
        new Yiisoft\Log\ContextProvider\CommonContextProvider(['environment' => 'production'])
    ),
);
```

## Документация

- [Руководство Yii по логированию](https://github.com/yiisoft/docs/blob/master/guide/en/runtime/logging.md)
- [Internals](../../internals.md)
