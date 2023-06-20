<?php

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

function check_email(string $email): int
{
    $sleep = rand(1, 60);
    sleep($sleep);
    return random_int(0, 1);
}

function send_mail(string $from, string $to, string $text): void
{
    $sleep = rand(1, 10);
    sleep($sleep);
}

function get_pdo(): PDO
{
    return new PDO("pgsql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
}

function get_amqp_connection(): AMQPStreamConnection
{
    return new AMQPStreamConnection($_ENV['AMQP_HOST'], $_ENV['AMQP_PORT'], $_ENV['AMQP_USER'], $_ENV['AMQP_PASSWORD']);
}

function get_logger(string $name = 'name'): Logger
{
    $log = new Logger($name);
    $log->pushHandler(new StreamHandler(__DIR__ . '/var/log/app.log', Level::Info));
    return $log;
}
