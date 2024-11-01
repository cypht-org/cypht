<?php

return [
    'aws_key' => env('AWS_ACCESS_KEY_ID',""),
    'aws_secret' => env('AWS_SECRET_ACCESS_KEY',""),
    'aws_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    // 'sqs_queue_url' => env('AWS_SQS_QUEUE_URL', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
    'sqs_queue' => env('SQS_QUEUE', 'default'),
];