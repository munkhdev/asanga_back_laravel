<?php

declare(strict_types=1);

return [
    'base_url' => env('QPAY_URL', ''),
    'username' => env('QPAY_USERNAME', ''),
    'password' => env('QPAY_PASSWORD', ''),
    'invoice_code' => env('QPAY_INVOICE_CODE', ''),
    'invoice_description' => env('QPAY_INVOICE_DESCRIPTION', 'Төлбөр'),
    'callback_base_url' => env('QPAY_CALLBACK_BASE_URL', ''),
    'sender_branch_code' => env('QPAY_SENDER_BRANCH_CODE', 'branch'),
    'receiver_code' => env('QPAY_RECEIVER_CODE', 'terminal'),
    'receiver_phone' => env('QPAY_RECEIVER_PHONE', '88200314'),
];
