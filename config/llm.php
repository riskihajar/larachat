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
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
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
        'openai' => 'OpenAI',
        'bedrock' => 'AWS Bedrock',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Models (Grouped by Provider)
    |--------------------------------------------------------------------------
    */

    'models' => [
        'openai' => [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ],
        'bedrock' => [
            'us.anthropic.claude-sonnet-4-20250514-v1:0' => 'Claude Sonnet 4.5',
            'us.anthropic.claude-3-7-sonnet-20250219-v3:0' => 'Claude Sonnet 3.7',
            'us.anthropic.claude-3-5-sonnet-20241022-v2:0' => 'Claude Sonnet 3.5',
            'us.anthropic.claude-3-5-haiku-20241022-v1:0' => 'Claude Haiku 3.5',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models per Provider
    |--------------------------------------------------------------------------
    |
    | These models will be used as fallback for existing chats
    | that don't have a model specified yet
    |
    */

    'default_models' => [
        'openai' => 'gpt-4o',
        'bedrock' => 'us.anthropic.claude-sonnet-4-20250514-v1:0',
    ],
];
