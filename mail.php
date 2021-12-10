<?php

use DI\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

define('BASE_DIR', __DIR__);

require(BASE_DIR. '/vendor/autoload.php');

$dotenv = new Dotenv();
$dotenv->load(BASE_DIR . '/.env');

$containerBuilder = new ContainerBuilder();
$container = $containerBuilder
    ->addDefinitions(BASE_DIR . '/definitions.php')
    ->build();

$email = (new Email())
    ->from($_ENV['SMTP_FROM'])
    ->to($_ENV['SMTP_TO'])
    ->subject('Test Email')
    ->text('Test Email');

$container->get(MailerInterface::class)->send($email);

echo 'Skrypt zakończono' . PHP_EOL . PHP_EOL;
