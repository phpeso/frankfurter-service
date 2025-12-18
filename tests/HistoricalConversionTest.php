<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Types\Decimal;
use Peso\Services\FrankfurterService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class HistoricalConversionTest extends TestCase
{
    public function testCurrentConv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1421.23', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'USD', 'CNY', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('8867', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'CNY', 'PHP', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('9661', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());
    }

    public function testCurrentMulticonv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(
            multiconversion: true,
            cache: $cache,
            httpClient: $http,
        );
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1421.23', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'JPY', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('204863', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'PHP', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('79883', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(1, $http->getRequests());

        // different amount

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('12.3456'), 'EUR', 'USD', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('14.2123', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        // different currency

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'USD', 'EUR', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1072.41', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        self::assertCount(3, $http->getRequests());
    }

    public function testCurrentMulticonvSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(
            symbols: ['USD', 'JPY', 'PHP', 'BYN'],
            multiconversion: true,
            cache: $cache,
            httpClient: $http,
        );
        $date = Calendar::parse('2025-06-13');

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1421.23', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'JPY', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('204863', $response->amount->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'EUR', 'TRY', $date),
        );
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1234.56 EUR to TRY on 2025-06-13', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests());
    }

    public function testInvalidBaseCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init(1), 'XBT', 'USD', Calendar::parse('2025-06-13')),
        );
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 XBT to USD on 2025-06-13', $response->exception->getMessage());
    }

    public function testInvalidQuoteCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new FrankfurterService(cache: $cache, httpClient: $http);

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init(1), 'USD', 'XBT', Calendar::parse('2025-06-13')),
        );
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 USD to XBT on 2025-06-13', $response->exception->getMessage());
    }
}
