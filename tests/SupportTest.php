<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Types\Decimal;
use Peso\Services\CurrencyApiService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SupportTest extends TestCase
{
    public function testRequests(): void
    {
        $service = new CurrencyApiService('xxxfreexxx');

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'USD')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'USD', Date::today())));
        self::assertTrue($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));

        self::assertFalse($service->supports(
            new CurrentConversionRequest(new Decimal('1000'), 'USD', 'EUR'),
        ));
        self::assertFalse($service->supports(
            new HistoricalConversionRequest(new Decimal('1000'), 'USD', 'EUR', Date::today()),
        ));

        self::assertFalse($service->supports(new stdClass()));
    }
}
