<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Services\LLM\Providers\OpenAIProvider;
use App\Services\LLM\Providers\BedrockProvider;
use App\Services\LLM\LLMProviderFactory;
use App\Services\ToolCoordinator;

it('factory creates correct provider instances', function () {
    // Test OpenAI provider creation
    $openaiProvider = LLMProviderFactory::make('openai');
    expect($openaiProvider)->toBeInstanceOf(OpenAIProvider::class);
    expect($openaiProvider->getName())->toBe('openai');

    // Test Bedrock provider creation
    $bedrockProvider = LLMProviderFactory::make('bedrock');
    expect($bedrockProvider)->toBeInstanceOf(BedrockProvider::class);
    expect($bedrockProvider->getName())->toBe('bedrock');
});

it('injects correct provider and uses it for tool selection', function () {
    // Test that provider injection works correctly
    $openaiProvider = LLMProviderFactory::make('openai');
    $toolCoordinator = new ToolCoordinator($openaiProvider);

    // Access private method via reflection to check provider
    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);

    expect($injectedProvider)->toBeInstanceOf(OpenAIProvider::class);
    expect($injectedProvider->getName())->toBe('openai');
});

it('injects Bedrock provider correctly', function () {
    // Test that Bedrock provider injection works
    $bedrockProvider = LLMProviderFactory::make('bedrock');
    $toolCoordinator = new ToolCoordinator($bedrockProvider);

    // Access private method via reflection to check provider
    $reflection = new ReflectionClass($toolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);

    $injectedProvider = $llmProviderProperty->getValue($toolCoordinator);

    expect($injectedProvider)->toBeInstanceOf(BedrockProvider::class);
    expect($injectedProvider->getName())->toBe('bedrock');
});

it('complete integration flow from factory to tool coordinator', function () {
    // Test complete integration: Factory → Provider → ToolCoordinator
    
    // OpenAI Flow
    $openaiProvider = LLMProviderFactory::make('openai');
    $openaiToolCoordinator = new ToolCoordinator($openaiProvider);
    
    $reflection = new ReflectionClass($openaiToolCoordinator);
    $llmProviderProperty = $reflection->getProperty('llmProvider');
    $llmProviderProperty->setAccessible(true);
    
    $openaiInjectedProvider = $llmProviderProperty->getValue($openaiToolCoordinator);
    expect($openaiInjectedProvider->getName())->toBe('openai');
    
    // Bedrock Flow  
    $bedrockProvider = LLMProviderFactory::make('bedrock');
    $bedrockToolCoordinator = new ToolCoordinator($bedrockProvider);
    
    $bedrockInjectedProvider = $llmProviderProperty->getValue($bedrockToolCoordinator);
    expect($bedrockInjectedProvider->getName())->toBe('bedrock');
});