<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

final readonly class MockClient
{
    public static function get(): Client
    {
        $client = new Client();
        $client->setDefaultException(new \LogicException('Non-mocked URL'));

        $client->on(
            new RequestMatcher('/v1/latest', 'api.frankfurter.dev', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'amount=1&base=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-EUR.json', 'r'));

                    case 'amount=1&base=USD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-USD.json', 'r'));

                    case 'amount=1&base=PHP':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-PHP.json', 'r'));

                    case 'amount=1&base=EUR&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-EUR-EUR,USD.json', 'r'));

                    case 'amount=1&base=USD&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-USD-EUR,USD.json', 'r'));

                    case 'amount=1&base=PHP&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-PHP-EUR,USD.json', 'r'));

                    case 'amount=1234.56&base=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-1234.56-EUR.json', 'r'));

                    case 'amount=1234.56&base=EUR&symbols=USD':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-1234.56-EUR-USD.json', 'r'));

                    case 'amount=1234.56&base=EUR&symbols=USD%2CJPY%2CPHP%2CBYN':
                        return new Response(
                            body: fopen(__DIR__ . '/../data/conv/latest-1234.56-EUR-USD,JPY,PHP,BYN.json', 'r'),
                        );

                    case 'amount=12.3456&base=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-12.3456-EUR.json', 'r'));

                    case 'amount=1234.56&base=USD':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-1234.56-USD.json', 'r'));

                    case 'amount=1234.56&base=USD&symbols=PHP':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-1234.56-USD-PHP.json', 'r'));

                    case 'amount=1234.56&base=PHP&symbols=CNY':
                        return new Response(body: fopen(__DIR__ . '/../data/conv/latest-1234.56-PHP-CNY.json', 'r'));

                    case 'amount=1&base=XBT':
                    case 'amount=1&base=XBT&symbols=USD':
                    case 'amount=1&base=USD&symbols=XBT':
                        return new Response(status: 404, body: fopen(__DIR__ . '/../data/not-found.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked URL: ' . $request->getUri());
                }
            },
        );
        $client->on(
            new RequestMatcher('/v1/2025-06-13', 'api.frankfurter.dev', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'amount=1&base=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-EUR.json', 'r'));

                    case 'amount=1&base=EUR&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-EUR-EUR,USD.json', 'r'));

                    case 'amount=1&base=USD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-USD.json', 'r'));

                    case 'amount=1&base=USD&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-USD-EUR,USD.json', 'r'));

                    case 'amount=1&base=PHP':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-PHP.json', 'r'));

                    case 'amount=1&base=PHP&symbols=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-PHP-EUR,USD.json', 'r'));

                    case 'amount=1&base=XBT':
                        return new Response(status: 404, body: fopen(__DIR__ . '/../data/not-found.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked URL: ' . $request->getUri());
                }
            },
        );

        return $client;
    }
}
