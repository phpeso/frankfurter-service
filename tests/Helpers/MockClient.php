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

        $client->on(
            new RequestMatcher('/v1/latest', 'api.frankfurter.dev', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'amount=1&base=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-EUR.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked URL: ' . $request->getUri());
                }
            },
        );

        return $client;
    }
}
