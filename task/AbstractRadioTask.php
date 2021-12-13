<?php

namespace ChromiumJotForm\Task;

use HeadlessChromium\Dom\Node;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PageScreenshot;
use Predis\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractRadioTask
{
    protected function clickElement(Page $page, string $selector, int $sleep = 0): self
    {
        /** @var Node $vaccination */
        $vaccination = $page->dom()->search($selector)[0];
        $vaccination->click();

        sleep($sleep);

        return $this;
    }

    protected function getAvailableTimeSlots(Page $page, string $id, int $timeout = 1000): array
    {
        $timeSlotsBody = $page->evaluate(
            sprintf('document.getElementById("%s").contentWindow.document.getElementsByTagName("body")[0].innerHTML;', $id)
        )->getReturnValue($timeout);

        $crawler = new Crawler($timeSlotsBody);

        $texts = $crawler->filter('.checkbox:not(.line-through)')->each(fn (Crawler $listItem): string => $listItem->text());

        return array_filter($texts, fn (string $text): bool => $text !== 'keine Dienste mit MFA vorhanden');
    }

    protected function sendEmail(MailerInterface $mailer, string $from, array $to, array $availableTimeSlots, PageScreenshot $screenshot): void
    {
        $screenshot->saveToFile(BASE_DIR . '/screenshot.png');

        $email = (new Email())
            ->from($from)
            ->to(...$to)
            ->subject('Powiadomienie z eu.jotform.com/211681414001339')
            ->html(
                implode('', array_map(fn(string $availableTimeSlot): string => ($availableTimeSlot . '</br><br/>'), $availableTimeSlots))
            )
            ->attachFromPath(BASE_DIR . '/screenshot.png');

        $mailer->send($email);

        echo sprintf('Email został wysłany do "%s"', json_encode($to)) . PHP_EOL . PHP_EOL;
    }

    protected function getScreenshotLink(HttpClientInterface $httpClient, PageScreenshot $screenshot): string
    {
        $response = $httpClient->request('POST', '/3/image', [
            'body' => [
                'image' => $screenshot->getBase64()
            ]
        ]);

        return json_decode($response->getContent())->data->link;
    }

    protected function redisTimeSlotsAlreadyReported (Client $redis, array $timeSlots): bool
    {
        $previousTimeSlots = $redis->get('PREVIOUS_TIME_SLOTS');
        if (!$previousTimeSlots) {
            return false;
        }

        $previousTimeSlots = json_decode($previousTimeSlots);

        if (array_diff($previousTimeSlots, $timeSlots) == array_diff($timeSlots, $previousTimeSlots)) {
            return true;
        }

        return false;
    }

    protected function redisStoreTimeSlots (Client $redis, array $timeSlots): void
    {
        $redis->set('PREVIOUS_TIME_SLOTS', json_encode($timeSlots));
    }
}
