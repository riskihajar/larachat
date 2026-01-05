<?php

declare(strict_types=1);

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\LLM\Providers\BedrockProvider;
use App\Services\LLM\Providers\OpenAIProvider;
use App\Services\ToolCoordinator;

it('uses OpenAI provider model for tool selection', function () {
    $mockProvider = \Mockery::mock(OpenAIProvider::class);
    $mockProvider->shouldReceive('getName')->andReturn('openai');
    $mockProvider->shouldReceive('getModel')->andReturn('gpt-4o-mini');

    $toolCoordinator = new ToolCoordinator($mockProvider);

    $result = $toolCoordinator->processMessage('Sekarang jam berapa?');

    expect($result['needs_tool'])->toBeTrue();
});

it('uses Bedrock provider model for tool selection', function () {
    $mockProvider = \Mockery::mock(BedrockProvider::class);
    $mockProvider->shouldReceive('getName')->andReturn('bedrock');
    $mockProvider->shouldReceive('getModel')->andReturn('us.anthropic.claude-sonnet-4-20250514-v1:0');

    $toolCoordinator = new ToolCoordinator($mockProvider);

    $result = $toolCoordinator->processMessage('Sekarang jam berapa?');

    expect($result['needs_tool'])->toBeTrue();
});

it('processes datetime queries for OpenAI provider', function () {
    $mockProvider = \Mockery::mock(LLMProviderInterface::class);
    $mockProvider->shouldReceive('getName')->andReturn('openai');
    $mockProvider->shouldReceive('getModel')->andReturn('gpt-4o-mini');

    $toolCoordinator = new ToolCoordinator($mockProvider);

    $result = $toolCoordinator->processMessage('Sekarang jam berapa di Makassar?');

    expect($result['needs_tool'])->toBeTrue();
});
