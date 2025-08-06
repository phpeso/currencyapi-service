<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Responses\ConversionResponse;
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
}
