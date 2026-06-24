<?php
if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

$_BLOCKLANG['updateIn'] = 'обновится через';

// Checkout pages
$_BLOCKLANG['orderId'] = 'Заказ #';
$_BLOCKLANG['error']['btc']['title'] = 'Не удалось сгенерировать новый адрес Bitcoin';
$_BLOCKLANG['error']['btc']['message'] = 'Уведомление для администратора: Войдите в настройки платежных шлюзов, и воспользуйтесь Test Setup для диагностики проблемы.';
$_BLOCKLANG['error']['bch']['title'] = 'Не удалось сгенерировать новый адрес Bitcoin Cash';
$_BLOCKLANG['error']['bch']['message'] = 'Уведомление для администратора: Войдите в настройки платежных шлюзов, и воспользуйтесь Test Setup для диагностики проблемы.';
$_BLOCKLANG['error']['pending']['title'] = 'Платеж в обработке';
$_BLOCKLANG['error']['pending']['message'] = 'Дополнительные платежи по счету допускаются только после подтверждения текущей ожидающей транзакции.';
$_BLOCKLANG['error']['addressGeneration']['title'] = 'Не удалось сгенерировать новый адрес';
$_BLOCKLANG['payWith'] = 'Оплатить с помощью';
$_BLOCKLANG['paymentExpired'] = 'Истек срок платежа';
$_BLOCKLANG['tryAgain'] = 'Попробуйте еще раз';
$_BLOCKLANG['paymentError'] = 'Ошибка платежа';
$_BLOCKLANG['openWallet'] = 'Открыть в кошельке';
$_BLOCKLANG['payAmount1'] = 'Для оплаты, отправьте ';
$_BLOCKLANG['payAmount2'] = ' на этот адрес';
$_BLOCKLANG['payAddress1'] = 'Сумма '; 
$_BLOCKLANG['payAddress2'] = ' к отправке';
$_BLOCKLANG['copyClipboard'] = 'Скопировано в буфер';
$_BLOCKLANG['howToPay'] = 'Как оплатить?';
$_BLOCKLANG['poweredBy'] = 'Powered by Blockonomics';
$_BLOCKLANG['noCrypto']['title'] = 'Не активирована криптовалюта для оплаты';
$_BLOCKLANG['noCrypto']['message'] = 'Уведомление для администратора: Включите криптовалюты в > Payments > Payment Gateways > Blockonomics > Currencies';

// Callback
$_BLOCKLANG['error']['secret'] = 'Ошибка проверки секретного ключа';
$_BLOCKLANG['invoiceNote']['waiting'] = 'Ожидание подтверждения в сети';
$_BLOCKLANG['invoiceNote']['network'] = 'сеть';

// Admin Menu
$_BLOCKLANG['version']['title'] = 'Версия';
$_BLOCKLANG['apiKey']['title'] = 'API ключ';
$_BLOCKLANG['apiKey']['description'] = 'Чтобы получить API ключ, нажмите <b>Get Started for Free</b> на <a target="_blank" href="https://blockonomics.co/merchants">https://blockonomics.co/merchants</a>';
$_BLOCKLANG['enabled']['title'] = 'Включено';
$_BLOCKLANG['enabled']['btc_description'] = 'Для настройки нажмите <b>Get Started for Free</b> на <a target="_blank" href="https://blockonomics.co/merchants">https://blockonomics.co/merchants</a>';
$_BLOCKLANG['enabled']['bch_description'] = 'Для настройки нажмите <b>Get Started for Free</b> на <a target="_blank" href="https://bch.blockonomics.co/merchants">https://bch.blockonomics.co/merchants</a>';
$_BLOCKLANG['callbackSecret']['title'] = 'Секретный ключ обратного вызова';
$_BLOCKLANG['callbackUrl']['title'] = 'URL обратного вызова';
$_BLOCKLANG['AvancedSettings']['title'] = 'Расширенные настройки ▼';
$_BLOCKLANG['timePeriod']['title'] = 'Временной период';
$_BLOCKLANG['timePeriod']['description'] = 'Период времени таймера обратного отсчета на странице оплаты (в минутах)';
$_BLOCKLANG['margin']['title'] = 'Дополнительная маржа<br>курса валюты %';
$_BLOCKLANG['margin']['description'] = 'Увеличить текущий курс обмена фиатной валюты на BTC на небольшой процент';
$_BLOCKLANG['slack']['title'] = 'Допустимое<br>отклонение %';
$_BLOCKLANG['slack']['description'] = 'Разрешить платежи, отличающиеся на небольшой процент';
$_BLOCKLANG['confirmations']['title'] = 'Подтверждения';
$_BLOCKLANG['confirmations']['description'] = 'Количество подтверждений сети, необходимых для завершения платежа';
$_BLOCKLANG['confirmations']['recommended'] = 'рекомендуется';

// Test Setup
$_BLOCKLANG['testSetup']['systemUrl']['error'] = 'Невозможно найти/выполнить';
$_BLOCKLANG['testSetup']['systemUrl']['fix'] = 'Проверьте ваш системный URL WHMCS. Перейдите в Настройки > Общие настройки и проверьте ваш системный URL WHMCS';
$_BLOCKLANG['testSetup']['success'] = 'Поздравляем! Настройка завершена';
$_BLOCKLANG['testSetup']['protocol']['error'] = 'Ошибка: Системный URL имеет другой протокол, чем текущий URL.';
$_BLOCKLANG['testSetup']['protocol']['fix'] = 'Перейдите в Настройки > Общие настройки и убедитесь, что системный URL WHMCS имеет правильный протокол (HTTP или HTTPS).';
$_BLOCKLANG['testSetup']['testing'] = 'Проверка настроек...';
$_BLOCKLANG['testSetup']['blockedHttps'] = 'Ваш сервер блокирует исходящие HTTPS-вызовы';
$_BLOCKLANG['testSetup']['emptyApi'] = 'API ключ не установлен. Пожалуйста, введите ваш API ключ.';
$_BLOCKLANG['testSetup']['incorrectApi'] = 'API ключ неверен';
$_BLOCKLANG['testSetup']['addStore'] = 'Пожалуйста, добавьте новый магазин на сайте Blockonomics';