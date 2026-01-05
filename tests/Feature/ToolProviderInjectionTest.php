<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Services\LLM\Providers\OpenAIProvider;
use App\Services\LLM\Providers\BedrockProvider;
use App\Services\LLM\LLMProviderFactory;
use App\Services\ToolCoordinator;

it('factory creates correct provider instances', function () {
    $openaiProvider = LLMProviderFactory::make('openai');
    expect($openaiProvider)->toBeInstanceOf(OpenAIProvider::class);
    expect($openaiProvider->getName())->toBe('openai');

    $bedrockProvider = LLMProviderFactory::make('bedrock');
    expect($bedrockProvider)->toBeInstanceOf(BedrockProvider::class);
    expect($bedrockProvider->getName())->toBe('bedrock');
});

it('injects correct provider and uses it for tool selection', function () {
    $openaiProvider = LLMProviderFactory::make('openai');
    $toolCoordinator = new ToolCoordinator($openaiProvider);

    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);

    expect($injectedProvider)->toBeInstanceOf(OpenAIProvider::class);
    expect($injectedProvider->getName())->toBe('openai');
});

it('injects Bedrock provider correctly', function () {
    $bedrockProvider = LLMProviderFactory::make('bedrock');
    $toolCoordinator = new ToolCoordinator($bedrockProvider);

    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);

    expect($injectedProvider)->toBeInstanceOf(BedrockProvider::class);
    expect($injectedProvider->getName())->toBe('bedrock');
});

it('complete integration flow from factory to tool coordinator', function () {
    $openaiProvider = LLMProviderFactory::make('openai');
    $openaiToolCoordinator = new ToolCoordinator($openaiProvider);

    $reflection = new ReflectionClass($openaiToolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $openaiInjectedProvider = $llmProviderProperty->getValue($openaiToolCoordinator);
    expect($openaiInjectedProvider->getName())->toBe('openai');

    $bedrockProvider = LLMProviderFactory::make('bedrock');
    $bedrockToolCoordinator = new ToolCoordinator($bedrockProvider);

    $bedrockInjectedProvider = $llmProviderProperty->getValue($bedrockToolCoordinator);
    expect($bedrockInjectedProvider->getName())->toBe('bedrock');
});

it('uses OpenAI provider model for tool selection', function () {
    $openaiProvider = LLMProviderFactory::make('openai', 'gpt-4o-mini');
    expect($openaiProvider->getModel())->toBe('gpt-4o-mini');

    $toolCoordinator = new ToolCoordinator($openaiProvider);

    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);
    expect($injectedProvider->getModel())->toBe('gpt-4o-mini');
});

it('uses Bedrock provider model for tool selection', function () {
    $bedrockModel = 'us.anthropic.claude-sonnet-4-20250514-v1:0';
    $bedrockProvider = LLMProviderFactory::make('bedrock', $bedrockModel);
    expect($bedrockProvider->getModel())->toBe($bedrockModel);

    $toolCoordinator = new ToolCoordinator($bedrockProvider);

    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);
    expect($injectedProvider->getModel())->toBe($bedrockModel);
});