<?php

use Campo\UserAgent;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Dom\Node;
use Laminas\Uri\Uri;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

require(__DIR__ . '/vendor/autoload.php');

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$uri = (new Uri())
    ->setScheme('https')
    ->setHost('eu.jotform.com')
    ->setPath('/211681414001339')
    ->setQuery([
        'vorname' => 'Krzysztof',
        'nachname' => 'Syput',
        'telefonnummer' => '(0049) 15258428149',
        'email' => 'kpsyput@gmail.com',
        'qualifikation' => 'Arzt / Ärztin ungebunden / angestellt'
    ])
    ->toString();

$dsn = vsprintf('smtp://%s:%s@%s:%s', [
    $_SERVER['SMTP_USERNAME'] ?? null,
    $_SERVER['SMTP_PASSWORD'] ?? null,
    $_SERVER['SMTP_SERVER'] ?? null,
    $_SERVER['SMTP_PORT'] ?? null
]);

$browserFactory = new BrowserFactory($_SERVER['CHROME_BINARY'] ?? null);

$browser = $browserFactory->createBrowser([
    'userAgent' => UserAgent::random(),
    'customFlags' => [
        '--no-sandbox',
        '--disable-web-security'
    ]
]);

try {
    $page = $browser->createPage();
    $page
        ->navigate($uri)
        ->waitForNavigation();

    /** @var Node $vaccination */
    $vaccination = $page->dom()->search('#input_106_0')[0];
    $vaccination->click();

    sleep(1);

    /** @var Node $mfa */
    $mfa = $page->dom()->search('#input_81_1')[0];
    $mfa->click();

    sleep(5);

    /** @var Node $timeSlot */
    $timeSlot = $page->dom()->search('#cid_102 iframe')[0];

    $timeSlotBody = $page->evaluate(
        'document.getElementById("customFieldFrame_103").contentWindow.document.getElementsByTagName("body")[0].innerHTML;'
    )->getReturnValue(1000);

    $crawler = new Crawler($timeSlotBody);
    $availableTimeSlots = $crawler->filter('.checkbox')->each(fn (Crawler $listItem): string => $listItem->text());

    $screenshot = $screenshotEmpty = $page->screenshot()->getBase64();

    if (count($availableTimeSlots) > 0) {
        echo json_encode($availableTimeSlots, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

        $to = array_values(
            array_filter([
                $_SERVER['SMTP_TO'] ?? null,
                $_SERVER['SMTP_TO_ADDITIONAL'] ?? null
            ])
        );

        $email = (new Email())
            ->from($_SERVER['SMTP_FROM'] ?? null)
            ->to(...$to)
            ->subject('Powiadomienie z eu.jotform.com/211681414001339')
            ->html(
                implode('', array_map(fn(string $availableTimeSlot): string => ($availableTimeSlot . '</br><br/>'), $availableTimeSlots))
            )
            ->attach($screenshot);

        (new Mailer(
            Transport::fromDsn($dsn))
        )->send($email);

        echo sprintf('Email to \'%s\' has been sent out!', json_encode($to, JSON_PRETTY_PRINT)) . PHP_EOL . PHP_EOL;
    }

    if (array_key_exists('IMGUR_CLIENT_ID', $_SERVER)) {
        $imgurClient = HttpClient::createForBaseUri('https://api.imgur.com');
        $response = $imgurClient->request('POST', '/3/image', [
            'headers' => [
                'Authorization' => sprintf('Client-ID %s', $_SERVER['IMGUR_CLIENT_ID'])
            ],
            'body' => [
                'image' => $screenshot
            ]
        ]);

        echo sprintf('Screenshot możesz zobaczyć pod nastepującym adresem: \'%s\'', json_decode($response->getContent())->data->link) . PHP_EOL . PHP_EOL;
    }
} finally {
    $browser->close();
}
