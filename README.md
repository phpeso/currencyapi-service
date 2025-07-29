# CurrencyAPI Client for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]
[![GitHub Actions]][GitHub Actions Link]
[![Codecov]][Codecov Link]

[Packagist]: https://img.shields.io/packagist/v/peso/currencyapi-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/currencyapi-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/currencyapi-service.svg?style=flat-square
[GitHub Actions]: https://img.shields.io/github/actions/workflow/status/phpeso/currencyapi-service/ci.yml?style=flat-square
[Codecov]: https://img.shields.io/codecov/c/gh/phpeso/currencyapi-service?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/currencyapi-service
[GitHub Actions Link]: https://github.com/phpeso/currencyapi-service/actions
[Codecov Link]: https://codecov.io/gh/phpeso/currencyapi-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[CurrencyAPI](https://currencyapi.com/).

## Installation

```bash
composer require peso/currencyapi-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/currencyapi-service php-http/discovery guzzlehttp/guzzle symfony/cache
```

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Peso\Services\CurrencyApiService;
use Peso\Services\CurrencyApiService\Subscription;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/../vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new CurrencyApiService('cur_live_...', Subscription::Free, cache: $cache);
$converter = new CurrencyConverter($service);

// 10777.50 as of 2025-07-30
echo $converter->convert('12500', 'USD', 'EUR', 2), PHP_EOL;
```

## Documentation

Read the full documentation here: <https://phpeso.org/v1.x/services/currencyapi.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/currencyapi-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
