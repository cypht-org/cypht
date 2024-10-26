<?php

return [
    'aws_key' => env('AWS_KEY',""),
    'aws_secret' => env('AWS_SECRET',""),
    'aws_region' => env('AWS_REGION', 'us-east-1'),
    'sqs_queue_url' => env('AWS_SQS_QUEUE_URL', null),
];