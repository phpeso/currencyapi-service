<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Types\Decimal;
use Peso\Services\CurrencyApiService;
use Peso\Services\CurrencyApiService\Subscription;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CurrentConversionTest extends TestCase
{
    public function testCurrentConv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService('xxxpaidxxx', Subscription::Paid, cache: $cache, httpClient: $http);

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1434.7839204863', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'USD', 'RUB'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('99090.157790166', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'RUB', 'PHP'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('882.2607894103', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());
    }

    public function testCurrentMulticonv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService(
            'xxxpaidxxx',
            Subscription::Paid,
            multiconversion: true,
            cache: $cache,
            httpClient: $http,
        );

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1434.7839204863', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'JPY'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('211542.00671481', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'PHP'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('82298.059629799', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        self::assertCount(1, $http->getRequests());

        // different amount

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('12.3456'), 'EUR', 'USD'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('14.3478392049', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        // different currency

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'USD', 'EUR'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1062.2773031101', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        self::assertCount(3, $http->getRequests());
    }

    public function testCurrentMulticonvSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService(
            'xxxpaidxxx',
            Subscription::Paid,
            symbols: ['USD', 'JPY', 'PHP', 'BYN'],
            multiconversion: true,
            cache: $cache,
            httpClient: $http,
        );

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'USD'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('1434.7839204863', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'JPY'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('211542.00671481', $response->amount->value);
        self::assertEquals('2025-08-06', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'EUR', 'TRY'),
        );
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1234.56 EUR to TRY', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests());
    }

    public function testInvalidBaseCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService('xxxpaidxxx', Subscription::Paid, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(Decimal::init(1), 'XBT', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 XBT to USD', $response->exception->getMessage());
    }

    public function testInvalidQuoteCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CurrencyApiService('xxxpaidxxx', Subscription::Paid, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(Decimal::init(1), 'USD', 'XBT'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 USD to XBT', $response->exception->getMessage());
    }
}
