<?php

require_once __DIR__ . "/../bootstrap.php";
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;

$amqpConnection = get_amqp_connection();
$connection = get_pdo();
$logger = get_logger();

/**
 * Объявил и привязал очередь
 */
$channel = $amqpConnection->channel();
$channel->exchange_declare($_ENV['CHECK_MAIL_EXCHANGE'], 'direct', false, false, false);
$channel->queue_declare($_ENV['CHECK_MAIL_QUEUE'], false, false, false, false);
$channel->queue_bind($_ENV['CHECK_MAIL_QUEUE'], $_ENV['CHECK_MAIL_EXCHANGE']);

/**
 * Чтобы можно было запустить много параллельных consumer'ов настроил вычитывание из очереди по 1 сообщению
 */
$channel->basic_qos(null, 1, false);

/**
 * Чтобы консьюмеры не зависали, их периодически нужно убивать по таймеру или по количеству обработанных сообщений
 * Т.к. для функции check_email максимальный срок выполнения 60 секунд, то консьюмер либо обработает 10 сообщений, либо отработает 600 секурнд
 */
$timeout = 600; // Время в секундах
$maxMessages = 10; // Максимальное количество сообщений
$totalMessages = 0;
$startTimestamp = time();

$callback = function (AMQPMessage $message) use (&$totalMessages, $channel, $connection, $logger) {
    try {
        $connection->beginTransaction();


            $body = json_decode($message->getBody(), true);
            $isEmailValid = check_email($body['email']);
            $logger->info('Email successfully checked', ['email' => $body['email'], 'result' => $isEmailValid]);

            $insertQuery =$connection->prepare('UPDATE users SET valid = :valid WHERE id = :id');
            $insertQuery->execute(['valid' => $isEmailValid, 'id' => $body['id']]);

            if ($isEmailValid) {
                $channel->basic_publish($message, $_ENV['MAILING_LIST_EXCHANGE'], '');
                $logger->info('Email add to queue for send', ['id' => $body['id'], 'email' => $body['email']]);
            }

            $totalMessages++;
            $message->getChannel()->basic_ack($message->getDeliveryTag());

        $connection->commit();
    } catch (\Exception $exception) {
        $connection->rollBack();
        $message->getChannel()->basic_nack($message->getDeliveryTag(), false, true);
        $logger->critical('Error on email check:' . $exception->getMessage(), ['messageBody' => $message->getBody()]);
    }

};

$channel->basic_consume($_ENV['CHECK_MAIL_QUEUE'], '', false, false, false, false, $callback);

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
