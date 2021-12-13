<?php

namespace ChromiumJotForm\Task;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use Laminas\Uri\UriInterface;
use Predis\Client;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MfaSecondRadioTask extends AbstractRadioTask
{
    public function __invoke(Client $redis, HttpClientInterface $httpClient, MailerInterface $mailer, ProcessAwareBrowser $browser, UriInterface $uri, string $from, array $to = []): void
    {
        echo MfaSecondRadioTask::class . PHP_EOL;
        echo '======' . PHP_EOL . PHP_EOL;

        try {
            $page = $browser->createPage();
            $page
                ->navigate($uri)
                ->waitForNavigation();

            $this
                ->clickElement($page, '#input_106_0', 1)
                ->clickElement($page, '#input_81_1', $_ENV['WAIT_FOR_TIME_SLOTS_SEC'] ?? 10);

            $availableTimeSlots = $this->getAvailableTimeSlots($page, 'customFieldFrame_103');

            $screenshot = $page->screenshot();

            if (count($availableTimeSlots) > 0) {
                $this->sendEmail($mailer, $from, $to, $availableTimeSlots, $screenshot);
            }

            $screenshotLink = $this->getScreenshotLink($httpClient, $screenshot);

            echo sprintf('Screenshot możesz zobaczyć pod nastepującym adresem: "%s"', $screenshotLink) . PHP_EOL . PHP_EOL;

            echo 'Task zakończono' . PHP_EOL . PHP_EOL;
        } finally {
            $browser->close();
        }
    }
}
