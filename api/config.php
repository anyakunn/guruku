<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'letup33',
        'user' => getenv('DB_USER') ?: 'letup33',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'endpoints' => [
        'liveStat' => '/api/live-tx.php',
        'login'    => '/api/auth.php?action=login',
    ],
    'liveTx' => [
        'maxRows' => 6,
        'sources' => [['mId' => '60979', 'game' => 'SLOT']],
    ],
    'limits' => ['minDeposit' => 1, 'minWithdraw' => 25, 'maxWithdraw' => 5000],
    'platform' => [
        'signUpMethod'      => 'SMSRegister',
        'memberBankAccount' => 1,
        'telegramBotUsername' => 'letup33_agent',
        'ticker' => "Welcome to Malaysia's top betting web, Join & earn 7% weekly commissions "
                  . "and Free RM11 for every downline. Enjoy VIP support and thunder-fast "
                  . "withdrawals, LETUP33 wishes you jackpot KAW KAW",
    ],
];