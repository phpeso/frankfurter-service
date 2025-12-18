<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Date\Calendar;
use Closure;
use DateInterval;
use Error;
use Override;
use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class FrankfurterService implements PesoServiceInterface
{
    private const LATEST_ENDPOINT = '/v1/latest?';
    private const HISTORICAL_ENDPOINT = '/v1/%s?';

    public string $hostname;

    public function __construct(
        string $hostname = 'https://api.frankfurter.dev',
        private array|null $symbols = null,
        private bool $multiconversion = false,
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
    ) {
        // prefix with https:// if a protocol is missing
        if (!preg_match('@^[a-z][a-z0-9+.-]+://.@i', $hostname)) {
            $hostname = 'https://' . $hostname;
        }
        $this->hostname = rtrim($hostname, '/');
    }

    #[Override]
    public function send(object $request): ExchangeRateResponse|ConversionResponse|ErrorResponse
    {
        if (
            $request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest ||
            $request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest
        ) {
            return self::performRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performRequest(
        // phpcs:ignore SlevomatCodingStandard.TypeHints.DNFTypeHintFormat.DisallowedWhitespaceAroundOperator
        CurrentExchangeRateRequest|HistoricalExchangeRateRequest|
        CurrentConversionRequest|HistoricalConversionRequest $request,
    ): ErrorResponse|ExchangeRateResponse|ConversionResponse {
        $current = $request instanceof CurrentExchangeRateRequest || $request instanceof CurrentConversionRequest;
        $conversion = $request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest;

        $query = [
            'amount' => $conversion ? $request->baseAmount->value : '1',
            'base' => $request->baseCurrency,
        ];

        if ($conversion === false || $this->multiconversion) {
            $query['symbols'] = $this->symbols === null ? null : implode(',', $this->symbols);
        } else {
            $query['symbols'] = $request->quoteCurrency;
        }

        if ($current) {
            $endpoint = $this->hostname . self::LATEST_ENDPOINT;
        } else {
            $endpoint = $this->hostname . \sprintf(self::HISTORICAL_ENDPOINT, $request->date->toString());
        }

        $url = $endpoint . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);

        $rates = $this->retrieveRates($url, static fn (
            RequestInterface $httpRequest,
            ResponseInterface $httpResponse,
        ) => new ErrorResponse(
            ExchangeRateNotFoundException::fromRequest(
                $request,
                HttpFailureException::fromResponse($httpRequest, $httpResponse),
            ),
        ));

        if ($rates instanceof ErrorResponse) {
            return $rates;
        }

        $rate = $rates['rates'][$request->quoteCurrency] ?? null;

        if ($rate === null) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        $date = Calendar::parseDateTimeString(
            $rates['date'] ??
            throw new Error('Unexpected response: date missing'),
        );

        return $conversion ?
            new ConversionResponse(Decimal::init($rate), $date) :
            new ExchangeRateResponse(Decimal::init($rate), $date);
    }

    /**
     * @psalm-param Closure(RequestInterface, ResponseInterface): ErrorResponse $errorResponse
     */
    private function retrieveRates(string $url, Closure $errorResponse): array|ErrorResponse
    {
        $cacheKey = 'peso|frankfurter|' . hash('sha1', $url);

        $rates = $this->cache->get($cacheKey);

        if ($rates !== null) {
            return $rates;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
            'Frankfurter',
            'peso/frankfurter-service',
            $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
        ));
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() === 404) {
            $body = json_decode((string)$response->getBody(), flags: JSON_OBJECT_AS_ARRAY); // do not throw
            $message = ($body ?: [])['message'] ?? '';
            if ($message === 'not found') {
                // do not throw
                return $errorResponse($request, $response);
            }
        }
        if ($response->getStatusCode() !== 200) {
            throw HttpFailureException::fromResponse($request, $response);
        }

        $rates = json_decode(
            (string)$response->getBody(),
            flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY,
        ) ?? throw new Error('No rates in the response');

        $this->cache->set($cacheKey, $rates, $this->ttl);

        return $rates;
    }

    #[Override]
    public function supports(object $request): bool
    {
        if (
            $request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest ||
            $request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest
        ) {
            return true;
        }

        return false;
    }
}
