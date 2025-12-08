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
    public static function make(?string $provider = null): LLMProviderInterface
    {
        $provider = $provider ?? config('llm.default');

        return match ($provider) {
            'openai' => new OpenAIProvider,
            'bedrock' => new BedrockProvider,
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
