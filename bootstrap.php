<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Dotenv\Dotenv;
use SpaceWeatherBot\Api\CachedHttpClient;
use SpaceWeatherBot\Api\GuzzleHttpClient;
use SpaceWeatherBot\Api\Noaa\NoaaSwpcClient;
use SpaceWeatherBot\Config\AppConfig;
use SpaceWeatherBot\Lang\Locale;
use SpaceWeatherBot\Lang\Translator;
use SpaceWeatherBot\Location\NominatimGeocoder;
use SpaceWeatherBot\Logger\LoggerFactory;
use SpaceWeatherBot\Service\SpaceWeatherService;
use SpaceWeatherBot\Storage\JsonUserSettingsRepository;
use SpaceWeatherBot\Telegram\TelegramClient;
use SpaceWeatherBot\Telegram\UpdateHandler;
use SpaceWeatherBot\Weather\OpenMeteoProvider;
use SpaceWeatherBot\Weather\OpenWeatherMapProvider;
use SpaceWeatherBot\Weather\WeatherService;

$projectRoot = __DIR__;

if (is_file($projectRoot . '/.env')) {
    (new Dotenv())->load($projectRoot . '/.env');
}

foreach (['cache', 'logs', 'users'] as $dir) {
    $path = $projectRoot . '/storage/' . $dir;

    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

$config = AppConfig::fromEnvironment($projectRoot);
$logger = LoggerFactory::create($config);

$httpClient = new GuzzleHttpClient(new GuzzleClient([
    'headers' => [
        // Nominatim (OpenStreetMap) requires a real identifying User-Agent
        // per its usage policy: https://operations.osmfoundation.org/policies/nominatim/
        'User-Agent' => 'SpaceWeatherBot/1.0 (+' . $config->githubRepo . ')',
    ],
]), $logger);
$cache = new FilesystemAdapter('noaa', 0, $config->cacheDir());
$cachedHttp = new CachedHttpClient($httpClient, $cache, $logger);
$noaa = new NoaaSwpcClient($cachedHttp);

$translator = new Translator($config->langDir());
$defaultLocale = Locale::fromString($config->defaultLocale);
$settingsRepository = new JsonUserSettingsRepository($config, $defaultLocale);
$weatherService = new SpaceWeatherService($noaa);

$groundWeatherProviders = [new OpenMeteoProvider($cachedHttp)];

if ($config->openWeatherMapApiKey !== null && $config->openWeatherMapApiKey !== '') {
    $groundWeatherProviders[] = new OpenWeatherMapProvider($cachedHttp, $config->openWeatherMapApiKey);
}

$groundWeatherService = new WeatherService($groundWeatherProviders, $logger);
$geocoder = new NominatimGeocoder(new GuzzleClient([
    'headers' => [
        'User-Agent' => 'SpaceWeatherBot/1.0 (+' . $config->githubRepo . ')',
    ],
]), $logger);

$telegram = new TelegramClient(new GuzzleClient(), $config->telegramBotToken, $logger);

$updateHandler = new UpdateHandler(
    $telegram,
    $translator,
    $settingsRepository,
    $weatherService,
    $groundWeatherService,
    $geocoder,
    $config,
    $defaultLocale,
    $logger,
);

return [
    'config' => $config,
    'logger' => $logger,
    'telegram' => $telegram,
    'updateHandler' => $updateHandler,
];
