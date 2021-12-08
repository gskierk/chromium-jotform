<?php

use Campo\UserAgent;
use ChromiumJotForm\Task\MfaFirstRadioTask;
use ChromiumJotForm\Task\MfaSecondRadioTask;
use DI\ContainerBuilder;
use HeadlessChromium\BrowserFactory;
use Symfony\Component\Dotenv\Dotenv;

define('BASE_DIR', __DIR__);

require(BASE_DIR. '/vendor/autoload.php');

$dotenv = new Dotenv();
$dotenv->load(BASE_DIR . '/.env');

$containerBuilder = new ContainerBuilder();
$container = $containerBuilder
    ->addDefinitions(BASE_DIR . '/definitions.php')
    ->build();

$tasks = [
    MfaFirstRadioTask::class,
    MfaSecondRadioTask::class
];

$to = array_values(
    array_filter([
        $_ENV['SMTP_TO'] ?? null,
        $_ENV['SMTP_TO_ADDITIONAL'] ?? null
    ])
);

$browserFactory = new BrowserFactory($_ENV['CHROME_BINARY'] ?? null);

foreach ($tasks as $task) {
    $browser = $browserFactory->createBrowser([
        'userAgent' => UserAgent::random(),
        'windowSize' => [1080, 4320],
        'customFlags' => [
            '--no-sandbox',
            '--disable-web-security'
        ]
    ]);

    $container->call($task, [
        'browser' => $browser,
        'from' => $_ENV['SMTP_FROM'],
        'to' => $to
    ]);
}

echo 'Skrypt zakończono' . PHP_EOL . PHP_EOL;
