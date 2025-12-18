<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\FrankfurterService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class HistoricalRateTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1512', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'PHP', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('56.207', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'JPY', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.5645', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date)); // cached
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('165.94', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(3, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(symbols: [
            'EUR', 'USD',
        ], cache: $cache, httpClient: $http);
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1512', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'EUR', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.86866', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // any to symbols is ok
        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'EUR', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.01545', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // symbols to missing is not OK
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'PHP', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'Unable to find exchange rate for EUR/PHP on 2025-06-13',
            $response->exception->getMessage(),
        );
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(new HistoricalExchangeRateRequest('XBT', 'USD', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'Unable to find exchange rate for XBT/USD on 2025-06-13',
            $response->exception->getMessage(),
        );
    }
}
