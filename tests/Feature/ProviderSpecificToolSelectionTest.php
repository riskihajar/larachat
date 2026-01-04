<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Services\LLM\Providers\OpenAIProvider;
use App\Services\LLM\Providers\BedrockProvider;
use App\Services\ToolCoordinator;
use App\Services\McpClient;

it('uses OpenAI API for tool selection when OpenAI provider is injected', function () {
    // Mock MCP client to return tools
    $this->mock(McpClient::class, function ($mock) {
        $mock->shouldReceive('getAvailableTools')
            ->once()
            ->andReturn([
                [
                    'name' => 'get-current-date-time-tool',
                    'description' => 'Get current date and time',
                    'inputSchema' => [
                        'properties' => [
                            'timezone' => [
                                'description' => 'Timezone to use',
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]);
    });

    // Mock OpenAI chat API
    $this->mock(\OpenAI\Laravel\Facades\OpenAI::class, function ($mock) {
        $mock->shouldReceive('chat->create')
            ->once()
            ->andReturn((object) [
                'choices' => [
                    (object) [
                        'message' => (object) [
                            'content' => 'get-current-date-time-tool'
                        ]
                    ]
                ]
            ]);
    });

    // Create ToolCoordinator with OpenAI provider
    $openaiProvider = new OpenAIProvider();
    $toolCoordinator = new ToolCoordinator($openaiProvider);

    // Process a datetime message
    $result = $toolCoordinator->processMessage('Sekarang jam berapa?');

    // Verify OpenAI was used for tool selection
    expect($result['needs_tool'])->toBeTrue();
    expect($result['tool_name'])->toBe('get-current-date-time-tool');
});

it('uses Bedrock API for tool selection when Bedrock provider is injected', function () {
    // Mock MCP client to return tools
    $this->mock(McpClient::class, function ($mock) {
        $mock->shouldReceive('getAvailableTools')
            ->once()
            ->andReturn([
                [
                    'name' => 'get-current-date-time-tool',
                    'description' => 'Get current date and time',
                    'inputSchema' => [
                        'properties' => [
                            'timezone' => [
                                'description' => 'Timezone to use',
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]);
    });

    // Mock HTTP client for Bedrock API
    $this->mock(\GuzzleHttp\Client::class, function ($mock) {
        $mock->shouldReceive('send')
            ->once()
            ->andReturn(
                $this->mock(\Psr\Http\Message\ResponseInterface::class, function ($responseMock) {
                    $responseMock->shouldReceive('getBody->getContents')
                        ->once()
                        ->andReturn(json_encode([
                            'content' => [
                                ['text' => 'get-current-date-time-tool']
                            ]
                        ]));
                })->getMock()
            );
    });

    // Mock AWS credentials and signature
    $this->mock(\Aws\Credentials\Credentials::class);
    $this->mock(\Aws\Signature\SignatureV4::class, function ($mock) {
        $mock->shouldReceive('signRequest')
            ->once()
            ->andReturn($this->mock(\Psr\Http\Message\RequestInterface::class)->getMock());
    });

    // Create ToolCoordinator with Bedrock provider
    $bedrockProvider = new BedrockProvider();
    $toolCoordinator = new ToolCoordinator($bedrockProvider);

    // Process a datetime message
    $result = $toolCoordinator->processMessage('Sekarang jam berapa?');

    // Verify Bedrock was used for tool selection
    expect($result['needs_tool'])->toBeTrue();
    expect($result['tool_name'])->toBe('get-current-date-time-tool');
});