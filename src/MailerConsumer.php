<?php

require_once __DIR__ . "/../bootstrap.php";

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;

$amqpConnection = get_amqp_connection();
$logger = get_logger();

/**
 * Объявил и привязал очередь
 */
$channel = $amqpConnection->channel();
$channel->exchange_declare($_ENV['MAILING_LIST_EXCHANGE'], 'direct', false, false, false);
$channel->queue_declare($_ENV['MAILING_LIST_QUEUE'], false, false, false, false);
$channel->queue_bind($_ENV['MAILING_LIST_QUEUE'], $_ENV['MAILING_LIST_EXCHANGE']);

/**
 * Чтобы можно было запустить много параллельных consumer'ов настроил вычитывание из очереди по 1 сообщению
 */
$channel->basic_qos(null, 1, false);

/**
 * Чтобы консьюмеры не зависали, их периодически нужно убивать по таймеру или по количеству обработанных сообщений
 * Т.к. для функции send_mail максимальный срок выполнения 10 секунд, то консьюмер либо обработает 60 сообщений, либо отработает 600 секурнд
 */
$timeout = 600; // Время в секундах
$maxMessages = 60; // Максимальное количество сообщений
$totalMessages = 0;
$startTimestamp = time();

$callback = function (AMQPMessage $message) use (&$totalMessages, $logger) {
    try {
        $body = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $text = "{$body['username']}, your subscription is expiring soon";
        send_mail(
            $_ENV['EMAIL_FROM'],
            $body['email'],
            $text
        );

        $logger->info('Message was sent', ['amqpMsg'=> $body, 'text' => $text]);
        $message->getChannel()->basic_ack($message->getDeliveryTag());
        $totalMessages++;
    } catch (\Exception $exception) {
        $logger->critical('Message send error:' . $exception->getMessage(), ['messageBody' => $message->getBody()]);
        $message->getChannel()->basic_nack($message->getDeliveryTag(), false, true);
    }

};

$channel->basic_consume($_ENV['MAILING_LIST_QUEUE'], '', false, false, false, false, $callback);

/**
 * условия для выхода из консьюмера, если достигнут лимит времени или лимит сообщений
 */
while ($channel->is_open() && $totalMessages < $maxMessages - 1 && (time() - $startTimestamp) <= $timeout) {
    try {
        $channel->wait(null, false, $timeout);
    } catch (AMQPTimeoutException $exception) {
        $logger->info('MailerConsumer timed out');
        break;
    }
}

$channel->close();
$amqpConnection->close();
