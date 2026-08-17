<?php

declare(strict_types=1);

namespace SpaceWeatherBot\Tests\Location;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SpaceWeatherBot\Lang\Locale;
use SpaceWeatherBot\Location\NominatimGeocoder;

final class NominatimGeocoderTest extends TestCase
{
    private function clientReturning(array $body): Client
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR)),
        ]);

        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    public function testPrefersCityAndDistrictWhenBothArePresent(): void
    {
        $client = $this->clientReturning([
            'display_name' => 'Some Long Full Address, Russia',
            'address' => [
                'city' => 'Moscow',
                'suburb' => 'Ostankinsky District',
            ],
        ]);

        $name = (new NominatimGeocoder($client, new NullLogger()))->reverseGeocode(55.822, 37.640, Locale::En);

        self::assertSame('Moscow, Ostankinsky District', $name);
    }

    public function testFallsBackToCityAloneWhenNoDistrict(): void
    {
        $client = $this->clientReturning([
            'display_name' => 'Some Long Full Address, Russia',
            'address' => ['town' => 'Khimki'],
        ]);

        $name = (new NominatimGeocoder($client, new NullLogger()))->reverseGeocode(55.9, 37.4, Locale::En);

        self::assertSame('Khimki', $name);
    }

    public function testFallsBackToDisplayNameWhenNoStructuredLocality(): void
    {
        $client = $this->clientReturning([
            'display_name' => 'Middle of Nowhere, Russia',
            'address' => ['country' => 'Russia'],
        ]);

        $name = (new NominatimGeocoder($client, new NullLogger()))->reverseGeocode(60.0, 60.0, Locale::En);

        self::assertSame('Middle of Nowhere, Russia', $name);
    }

    public function testReturnsNullWhenTheRequestFails(): void
    {
        $mock = new MockHandler([
            new ConnectException('Simulated timeout', new Request('GET', 'test')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $name = (new NominatimGeocoder($client, new NullLogger()))->reverseGeocode(55.822, 37.640, Locale::Ru);

        self::assertNull($name);
    }

    public function testReturnsNullWhenResponseIsNotValidJson(): void
    {
        $mock = new MockHandler([new Response(200, [], 'not json')]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $name = (new NominatimGeocoder($client, new NullLogger()))->reverseGeocode(55.822, 37.640, Locale::En);

        self::assertNull($name);
    }
}
