<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\Providers\BedrockProvider;
use App\Services\LLM\Providers\OpenAIProvider;
use InvalidArgumentException;

class LLMProviderFactory
{
    /**
     * Create an LLM provider instance.
     */
    public static function make(?string $provider = null, ?string $model = null): LLMProviderInterface
    {
        $provider = $provider ?? config('llm.default');
        $model = $model ?? config("llm.default_models.{$provider}");

        return match ($provider) {
            'openai' => new OpenAIProvider($model),
            'bedrock' => new BedrockProvider($model),
            default => throw new InvalidArgumentException("Unsupported LLM provider: {$provider}"),
        };
    }

    /**
     * Get all available providers.
     *
     * @return array<string, string>
     */
    public static function getAvailableProviders(): array
    {
        return config('llm.providers', []);
    }
}
