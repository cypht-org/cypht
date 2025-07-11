<?php

return [
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from' => env('VONAGE_FROM'),
    ],
    'slack' => [
        'channel' => env('SLACK_CHANNEL'),
        'token' => env('SLACK_TOKEN')
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'service_encrypt_secret_key' => env('SERVICE_ENCRYPT_SECRET_KEY', 'fSqFdw1RHBRfM9RWsDXgQqhtAZLy2KVwHMa6zBXm7qA='),

    'service_encrypt_dir' => env('SERVICE_ENCRYPT_DIR', '/var/lib/hm3/attachments')
];