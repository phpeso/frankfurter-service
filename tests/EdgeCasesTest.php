<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use ArrayObject;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\FrankfurterService;
use PHPUnit\Framework\TestCase;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidObject(): void
    {
        $service = new FrankfurterService();

        $response = $service->send(new ArrayObject());
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals(
            'Unsupported request type: "ArrayObject"',
            $response->exception->getMessage(),
        );
    }

    public function testHostname(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(body: fopen(__DIR__ . '/data/rates/latest-EUR.json', 'r')));

        $request = new CurrentExchangeRateRequest('EUR', 'USD');

        // auto prefixes with https
        $service = new FrankfurterService(hostname: 'api.self-hosted.local', httpClient: $http);
        $service->send($request);
        $httpRequest = $http->getLastRequest();
        self::assertEquals('https://api.self-hosted.local/v1/latest?amount=1&base=EUR', (string)$httpRequest->getUri());

        // keeps https
        $service = new FrankfurterService(hostname: 'https://api.self-hosted.local', httpClient: $http);
        $service->send($request);
        $httpRequest = $http->getLastRequest();
        self::assertEquals('https://api.self-hosted.local/v1/latest?amount=1&base=EUR', (string)$httpRequest->getUri());

        // keeps http
        $service = new FrankfurterService(hostname: 'http://api.self-hosted.local', httpClient: $http);
        $service->send($request);
        $httpRequest = $http->getLastRequest();
        self::assertEquals('http://api.self-hosted.local/v1/latest?amount=1&base=EUR', (string)$httpRequest->getUri());
    }

    public function testInternalError(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(500));

        $service = new FrankfurterService(httpClient: $http);

        $this->expectException(HttpFailureException::class);
        $this->expectExceptionMessage('HTTP error 500. Response is ""');
        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
    }

    public function testNonJson404(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(404, body: 'Not found'));

        $service = new FrankfurterService(httpClient: $http);

        $this->expectException(HttpFailureException::class);
        $this->expectExceptionMessage('HTTP error 404. Response is "Not found"');
        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
    }
}
