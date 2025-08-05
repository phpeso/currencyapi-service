<?php

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
use Peso\Services\CurrencyApiService\Subscription;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class CurrencyApiService implements PesoServiceInterface
{
    private const LATEST_ENDPOINT = 'https://api.currencyapi.com/v3/latest?';
    private const HISTORICAL_ENDPOINT = 'https://api.currencyapi.com/v3/historical?';
    private const CONVERT_ENDPOINT = 'https://api.currencyapi.com/v3/convert?';

    public function __construct(
        private string $apiKey,
        private Subscription $subscription,
        private array|null $symbols = null,
        private bool $multiconversion = false,
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
    ) {
    }

    #[Override]
    public function send(object $request): ExchangeRateResponse|ConversionResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest) {
            return self::performRateRequest($request);
        }
        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            return self::performConversionRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performRateRequest(
        CurrentExchangeRateRequest|HistoricalExchangeRateRequest $request,
    ): ErrorResponse|ExchangeRateResponse {
        $query = [
            'apikey' => $this->apiKey,
            'base_currency' => $request->baseCurrency,
            'currencies' => $this->symbols === null ? null : implode(',', $this->symbols),
        ];

        if ($request instanceof CurrentExchangeRateRequest) {
            $url = self::LATEST_ENDPOINT . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
        } else {
            $query['date'] = $request->date->toString();
            $url = self::HISTORICAL_ENDPOINT . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
        }

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

        $rate = $rates['data'][$request->quoteCurrency]['value'] ?? null;

        if ($rate === null) {
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        $date = Calendar::parseDateTimeString(
            $rates['meta']['last_updated_at'] ??
            throw new Error('Unexpected response: last_updated_at missing'),
        );

        return new ExchangeRateResponse(Decimal::init($rate), $date);
    }

    private function performConversionRequest(
        CurrentConversionRequest|HistoricalConversionRequest $request,
    ): ErrorResponse|ConversionResponse {
        if ($this->subscription !== Subscription::Paid) {
            return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
        }

        $query = [
            'apikey' => $this->apiKey,
            'base_currency' => $request->baseCurrency,
            'value' => $request->baseAmount->value,
        ];

        if ($this->multiconversion) {
            // convert all currencies or selected symbols
            $query['currencies'] = $this->symbols === null ? null : implode(',', $this->symbols);
        } else {
            $query['currencies'] = $request->quoteCurrency;
        }

        if ($request instanceof HistoricalConversionRequest) {
            $query['date'] = $request->date->toString();
        }

        $url = self::CONVERT_ENDPOINT . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);

        $amounts = $this->retrieveRates($url, static fn (
            RequestInterface $httpRequest,
            ResponseInterface $httpResponse,
        ) => new ErrorResponse(
            ConversionNotPerformedException::fromRequest(
                $request,
                HttpFailureException::fromResponse($httpRequest, $httpResponse),
            ),
        ));

        if ($amounts instanceof ErrorResponse) {
            return $amounts;
        }

        $amount = $amounts['data'][$request->quoteCurrency]['value'] ?? null;

        if ($amount === null) {
            return new ErrorResponse(ConversionNotPerformedException::fromRequest($request));
        }

        $date = Calendar::parseDateTimeString(
            $amounts['meta']['last_updated_at'] ??
            throw new Error('Unexpected response: last_updated_at missing'),
        );

        return new ConversionResponse(Decimal::init($amount), $date);
    }

    /**
     * @psalm-param Closure(RequestInterface, ResponseInterface): ErrorResponse $errorResponse
     */
    private function retrieveRates(string $url, Closure $errorResponse): array|ErrorResponse
    {
        $cacheKey = 'peso|curapi|' . hash('sha1', $url);

        $rates = $this->cache->get($cacheKey);

        if ($rates !== null) {
            return $rates;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
            'CurrencyAPI',
            'peso/currencyapi-service',
            $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
        ));
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() === 422) {
            // do not throw
            return $errorResponse($request, $response);
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
        if ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest) {
            return true;
        }

        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            return $this->subscription === Subscription::Paid;
        }

        return false;
    }
}
