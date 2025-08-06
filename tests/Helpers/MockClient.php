<?php

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
            new RequestMatcher('/v3/latest', 'api.currencyapi.com', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'apikey=xxxfreexxx&base_currency=EUR':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-EUR.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=USD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=RUB':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-RUB.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=EUR&currencies=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-EUR-EUR,USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=USD&currencies=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-USD-EUR,USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=RUB&currencies=EUR%2CUSD':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/latest-RUB-EUR,USD.json', 'r'));

                    case 'apikey=rate_exceeded&base_currency=TRY':
                        return new Response(429, body: fopen(__DIR__ . '/../data/rate-limit.json', 'r'));

                    case 'apikey=invalid&base_currency=TRY':
                        return new Response(401, body: fopen(__DIR__ . '/../data/invalid-key.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=XBT':
                        return new Response(422, body: fopen(__DIR__ . '/../data/invalid-base.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            },
        );
        $client->on(
            new RequestMatcher('/v3/historical', 'api.currencyapi.com', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'apikey=xxxfreexxx&base_currency=EUR&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-EUR.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=USD&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=RUB&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-RUB.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=EUR&currencies=EUR%2CUSD&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-EUR-EUR,USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=USD&currencies=EUR%2CUSD&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-USD-EUR,USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=RUB&currencies=EUR%2CUSD&date=2025-06-13':
                        return new Response(body: fopen(__DIR__ . '/../data/rates/2025-06-13-RUB-EUR,USD.json', 'r'));

                    case 'apikey=xxxfreexxx&base_currency=XBT&date=2025-06-13':
                        return new Response(422, body: fopen(__DIR__ . '/../data/invalid-base.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            },
        );

        return $client;
    }
}
