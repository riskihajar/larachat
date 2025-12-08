<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default LLM provider that will be used when
    | creating new chats. Supported: "openai", "bedrock"
    |
    */

    'default' => env('LLM_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_MODEL', 'gpt-4'),
        'title_model' => env('OPENAI_TITLE_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Bedrock Configuration
    |--------------------------------------------------------------------------
    */

    'bedrock' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'model' => env('AWS_BEDROCK_DEFAULT_MODEL', 'us.anthropic.claude-sonnet-4-20250514-v1:0'),
        'title_model' => env('AWS_BEDROCK_TITLE_MODEL', 'us.anthropic.claude-3-5-haiku-20241022-v1:0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Display Names
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'openai' => 'OpenAI GPT-4',
        'bedrock' => 'Claude (AWS Bedrock)',
    ],
];
