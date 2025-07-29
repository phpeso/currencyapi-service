<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\CurrencyApiService;
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

        $service = new CurrencyApiService('xxxfreexxx', Subscription::Free, cache: $cache, httpClient: $http);
        $today = Calendar::parse('2025-07-28');

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1598235485', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('USD', 'RUB'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('81.3219983351', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('RUB', 'JPY'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.8258420785', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY')); // cached
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('172.2119069792', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        self::assertCount(3, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService('xxxfreexxx', Subscription::Free, symbols: [
            'EUR', 'USD',
        ], cache: $cache, httpClient: $http);
        $today = Calendar::parse('2025-07-28');

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.1598235485', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('USD', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.8622001177', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        // any to symbols is ok
        $response = $service->send(new CurrentExchangeRateRequest('RUB', 'EUR'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.0106022987', $response->rate->value);
        self::assertEquals($today, $response->date->toString());

        // symbols to missing is not OK
        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'RUB'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for EUR/RUB', $response->exception->getMessage());
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService('xxxfreexxx', Subscription::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('XBT', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for XBT/USD', $response->exception->getMessage());
    }
}
