<?php

use Campo\UserAgent;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use Laminas\Uri\Uri;
use Laminas\Uri\UriInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return [
    UriInterface::class => function(): UriInterface {
        return (new Uri())
            ->setScheme('https')
            ->setHost('eu.jotform.com')
            ->setPath('/211681414001339')
            ->setQuery([
                'vorname' => 'Krzysztof',
                'nachname' => 'Syput',
                'telefonnummer' => '(0049) 15258428149',
                'email' => 'kpsyput@gmail.com',
                'qualifikation' => 'Arzt / Ärztin ungebunden / angestellt'
            ]);
    },
    MailerInterface::class => function(): MailerInterface {
        $dsn = vsprintf('smtp://%s:%s@%s:%s', [
            $_ENV['SMTP_USERNAME'] ?? null,
            $_ENV['SMTP_PASSWORD'] ?? null,
            $_ENV['SMTP_SERVER'] ?? null,
            $_ENV['SMTP_PORT'] ?? null
        ]);

        return new Mailer(
            Transport::fromDsn($dsn)
        );
    },
    HttpClientInterface::class => function(): HttpClientInterface {
        return HttpClient::createForBaseUri('https://api.imgur.com', [
            'headers' => [
                'Authorization' => sprintf('Client-ID %s', $_ENV['IMGUR_CLIENT_ID'])
            ]
        ]);
    }
];
