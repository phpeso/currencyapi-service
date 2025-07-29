<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Types\Decimal;
use Peso\Services\CurrencyApiService;
use Peso\Services\CurrencyApiService\Subscription;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidRequest(): void
    {
        $service = new CurrencyApiService('xxx', Subscription::Free);

        $response = $service->send(new CurrentConversionRequest(Decimal::init('100'), 'TRY', 'PHP'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals(
            'Unsupported request type: "Peso\Core\Requests\CurrentConversionRequest"',
            $response->exception->getMessage(),
        );
    }

    public function testInvalidKey(): void
    {
        $http = MockClient::get();
        $service = new CurrencyApiService('invalid', Subscription::Free, httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage('Invalid authentication credentials');
        $service->send(new CurrentExchangeRateRequest('TRY', 'PHP'));
    }
}
