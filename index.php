<?php

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Dom\Node;
use Laminas\Uri\Uri;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Dotenv\Dotenv;
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
    $_SERVER['SMTP_USERNAME'],
    $_SERVER['SMTP_PASSWORD'],
    $_SERVER['SMTP_SERVER'],
    $_SERVER['SMTP_PORT']
]);

$browserFactory = new BrowserFactory($_SERVER['CHROME_BINARY'] ?? null);

$browser = $browserFactory->createBrowser([
    'customFlags' => [
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

    if (count($availableTimeSlots) > 0) {
        $page->screenshot()->saveToFile(__DIR__ . '/screenshot.png');

        $email = (new Email())
            ->from($_SERVER['SMTP_FROM'])
            ->to($_SERVER['SMTP_TO'])
            ->subject('Powiadomienie z eu.jotform.com/211681414001339')
            ->html(
                implode('', array_map(fn(string $availableTimeSlot): string => ($availableTimeSlot . '</br><br/>'), $availableTimeSlots))
            )
            ->attachFromPath(__DIR__ . '/screenshot.png');

        (new Mailer(
            Transport::fromDsn($dsn))
        )->send($email);
    }
} finally {
    $browser->close();
}