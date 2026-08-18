<?php

return [
    [
        'key'    => 'sales.payment_methods.bdpay',
        'name'   => 'PAGI',
        'info'   => 'Payment Aggregator & Gateway Indonesia (PAGI) Settings (Virtual Account, QRIS, e-Wallet)',
        'sort'   => 1,
        'fields' => [
            [
                'name'          => 'title',
                'title'         => 'Title',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => true,
                'locale_based'  => true,
            ],
            [
                'name'          => 'description',
                'title'         => 'Description',
                'type'          => 'textarea',
                'channel_based' => true,
                'locale_based'  => true,
            ],
            [
                'name'          => 'active',
                'title'         => 'Status',
                'type'          => 'boolean',
                'validation'    => 'required',
                'channel_based' => false,
                'locale_based'  => false,
            ],
            [
                'name'          => 'environment',
                'title'         => 'Environment',
                'type'          => 'select',
                'options'       => [
                    ['title' => 'Sandbox', 'value' => 'sandbox'],
                    ['title' => 'Production', 'value' => 'production'],
                ],
                'channel_based' => false,
            ],
            [
                'name'          => 'merchant_code',
                'title'         => 'Merchant Code',
                'type'          => 'text',
                'validation'    => 'required',
                'channel_based' => false,
            ],
            [
                'name'          => 'secret_key',
                'title'         => 'Secret Key',
                'type'          => 'password',
                'validation'    => 'required',
                'channel_based' => false,
            ],
            [
                'name'          => 'public_key',
                'title'         => 'Public Key (optional)',
                'type'          => 'textarea',
                'channel_based' => false,
            ],
            [
                'name'          => 'sort',
                'title'         => 'Sort Order',
                'type'          => 'text',
                'channel_based' => false,
            ],
        ],
    ],
];
