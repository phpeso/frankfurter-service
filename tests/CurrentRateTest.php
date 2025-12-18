<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\FrankfurterService;
use Peso\Services\CurrencyApiService\Subscription;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CurrentRateTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);
        $today = Calendar::parse('2025-12-17');

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1722', $response->rate->value);
        self::assertEquals($today, $response->date);

        $response = $service->send(new CurrentExchangeRateRequest('USD', 'PHP'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('58.669', $response->rate->value);
        self::assertEquals($today, $response->date);

        $response = $service->send(new CurrentExchangeRateRequest('PHP', 'JPY'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.652', $response->rate->value);
        self::assertEquals($today, $response->date);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY')); // cached
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('182.38', $response->rate->value);
        self::assertEquals($today, $response->date);

        self::assertCount(3, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(symbols: [
            'EUR', 'USD',
        ], cache: $cache, httpClient: $http);
        $today = Calendar::parse('2025-12-17');

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1722', $response->rate->value);
        self::assertEquals($today, $response->date);

        $response = $service->send(new CurrentExchangeRateRequest('USD', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.8531', $response->rate->value);
        self::assertEquals($today, $response->date);

        // any to symbols is ok
        $response = $service->send(new CurrentExchangeRateRequest('PHP', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.01454', $response->rate->value);
        self::assertEquals($today, $response->date);

        // symbols to missing is not OK
        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'PHP'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/PHP', $response->exception->getMessage());
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('XBT', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for XBT/USD', $response->exception->getMessage());
    }
}
