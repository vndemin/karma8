#!/bin/bash
docker-compose exec -T cron php /var/www/karma8/src/MailingListPreparer.php -d=1 &
docker-compose exec -T cron php /var/www/karma8/src/MailingListPreparer.php -d=3 &
docker-compose exec -T cron php /var/www/karma8/src/CheckerConsumer.php &
docker-compose exec -T cron php /var/www/karma8/src/CheckerConsumer.php &
docker-compose exec -T cron php /var/www/karma8/src/MailerConsumer.php &
docker-compose exec -T cron php /var/www/karma8/src/MailerConsumer.php &
