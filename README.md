# Frankfurter.dev Client for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]
[![GitHub Actions]][GitHub Actions Link]
[![Codecov]][Codecov Link]

[Packagist]: https://img.shields.io/packagist/v/peso/frankfurter-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/frankfurter-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/frankfurter-service.svg?style=flat-square
[GitHub Actions]: https://img.shields.io/github/actions/workflow/status/phpeso/frankfurter-service/ci.yml?style=flat-square
[Codecov]: https://img.shields.io/codecov/c/gh/phpeso/frankfurter-service?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/frankfurter-service
[GitHub Actions Link]: https://github.com/phpeso/frankfurter-service/actions
[Codecov Link]: https://codecov.io/gh/phpeso/frankfurter-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[Frankfurter.dev](https://frankfurter.dev/).

## Installation

```bash
composer require peso/frankfurter-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/frankfurter-service php-http/discovery guzzlehttp/guzzle symfony/cache
```

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Peso\Services\FrankfurterService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new FrankfurterService(cache: $cache);
$converter = new CurrencyConverter($service);

// 10664.00 as of 2025-12-18
echo $converter->convert('12500', 'USD', 'EUR', 2), PHP_EOL;
```

## Documentation

Read the full documentation here: <https://phpeso.org/v1.x/services/frankfurter.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/frankfurter-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
