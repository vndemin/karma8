<?php

require_once __DIR__ . "/../bootstrap.php";

use \PhpAmqpLib\Message\AMQPMessage;

$options = getopt("d:");

$limit = $_ENV['DEFAULT_BATCH_SIZE'];
$offset = 0;
$delta = $options['d'] ?? 1; // Количество дней до истечения подписки
$periodStarts = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+' . $delta . ' days');
$periodEnds = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+' . $delta + 1 . ' days');

$connection = get_pdo();
$amqpConnection = get_amqp_connection();
$channel = $amqpConnection->channel();
$channel->exchange_declare($_ENV['MAILING_LIST_EXCHANGE'], 'direct', false, false, false);
$channel->exchange_declare($_ENV['CHECK_MAIL_EXCHANGE'], 'direct', false, false, false);

do {
    $getUsersQuery = $connection->prepare('
        SELECT *
        FROM users
        WHERE 
            (   
                confirmed = true
                OR valid = true
                OR (confirmed = false AND valid IS NULL)
            )
            AND validts >= :periodStarts AND validts < :periodEnds 
        LIMIT :limit
        OFFSET :offset
    ');

    $getUsersQuery->execute(
        [
            'periodStarts' => $periodStarts->format('Y-m-d'),
            'periodEnds' => $periodEnds->format('Y-m-d'),
            'limit' => $limit,
            'offset' => $offset,
        ]
    );

    $users = $getUsersQuery->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $payload = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ];
        $message = new AMQPMessage(json_encode($payload));

        /**
         * Если email подтвержден или валиден, то сразу можно отправлять
         */
        if (true === $user['confirmed'] || true === $user['valid']) {
            $channel->basic_publish($message, $_ENV['MAILING_LIST_EXCHANGE'], '');
        }

        /**
         * Если email не подтвержден и не проверен, то отправляем его на проверку
         * После прохождения проверки, если будет успешна, то consumer сам отправит событие в exchange event.mailing_list
         */
        if (false === $user['confirmed'] && null === $user['valid']) {
            $channel->basic_publish($message, $_ENV['CHECK_MAIL_EXCHANGE'], '');
        }
    }
    $offset += $limit;
} while (count($users) > 0);

$channel->close();
$amqpConnection->close();
unset($connection);

