<?php

return [
    'default' => env('PAYMENT_GATEWAY', 'zarinpal'),

    'gateways' => [
        'zarinpal' => [
            'enabled' => (bool) env('ZARINPAL_ENABLED', false),
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', true),
            'callback_url' => env('ZARINPAL_CALLBACK_URL'),
            'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
            'api_url' => [
                'payment' => env('ZARINPAL_SANDBOX', true)
                    ? 'https://sandbox.zarinpal.com/pg/services/WebGate/wsdl'
                    : 'https://www.zarinpal.com/pg/services/WebGate/wsdl',
                'verify' => env('ZARINPAL_SANDBOX', true)
                    ? 'https://sandbox.zarinpal.com/pg/services/Verification/wsdl'
                    : 'https://www.zarinpal.com/pg/services/Verification/wsdl',
                'start' => env('ZARINPAL_SANDBOX', true)
                    ? 'https://sandbox.zarinpal.com/pg/StartPay/'
                    : 'https://www.zarinpal.com/pg/StartPay/',
            ],
        ],

        'idpay' => [
            'enabled' => (bool) env('IDPAY_ENABLED', false),
            'api_key' => env('IDPAY_API_KEY'),
            'sandbox' => (bool) env('IDPAY_SANDBOX', true),
            'callback_url' => env('IDPAY_CALLBACK_URL'),
            'currency' => env('IDPAY_CURRENCY', 'IRR'),
            'api_url' => [
                'token' => env('IDPAY_SANDBOX', true)
                    ? 'https://sandbox.idpay.ir/api/v1.1/payment'
                    : 'https://api.idpay.ir/v1.1/payment',
                'verify' => env('IDPAY_SANDBOX', true)
                    ? 'https://sandbox.idpay.ir/api/v1.1/payment/verify'
                    : 'https://api.idpay.ir/v1.1/payment/verify',
                'inquiry' => env('IDPAY_SANDBOX', true)
                    ? 'https://sandbox.idpay.ir/api/v1.1/payment/inquiry'
                    : 'https://api.idpay.ir/v1.1/payment/inquiry',
            ],
        ],
    ],

    'reconciliation' => [
        'enabled' => (bool) env('PAYMENT_RECONCILIATION_ENABLED', false),
        'schedule' => env('PAYMENT_RECONCILIATION_SCHEDULE', 'daily'),
        'retention_days' => (int) env('PAYMENT_RECONCILIATION_RETENTION_DAYS', 90),
    ],

    'refunds' => [
        'enabled' => (bool) env('REFUNDS_ENABLED', true),
        'max_refund_amount' => (int) env('MAX_REFUND_AMOUNT', 0),
        'refund_fee_percentage' => (float) env('REFUND_FEE_PERCENTAGE', 0),
    ],

    'settlement' => [
        'clinic_payout_percentage' => (float) env('CLINIC_PAYOUT_PERCENTAGE', 90),
        'platform_fee_percentage' => (float) env('PLATFORM_FEE_PERCENTAGE', 10),
        'settlement_cycle' => env('SETTLEMENT_CYCLE', 'daily'),
    ],
];
