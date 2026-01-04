<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\ToolCoordinator;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class BedrockProvider implements LLMProviderInterface
{
    protected BedrockRuntimeClient $client;

    protected string $model;

    protected string $titleModel;

    protected ?ToolCoordinator $toolCoordinator = null;

    public function __construct(?string $model = null)
    {
        $this->client = new BedrockRuntimeClient([
            'region' => config('llm.bedrock.region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('llm.bedrock.key'),
                'secret' => config('llm.bedrock.secret'),
            ],
        ]);

        $this->model = $model ?? config('llm.bedrock.model');
        $this->titleModel = config('llm.bedrock.title_model', $this->model);
    }

    /**
     * Enable tool calling support.
     */
    public function withTools(): self
    {
        $this->toolCoordinator = new ToolCoordinator($this);
        return $this;
    }

    /**
     * Stream response with function calling support for Bedrock/Claude.
     * Simplified approach similar to OpenAI.
     */
    public function streamWithTools(array $messages, array $userContext = []): \Generator
    {
        // Check if tools are enabled
        if (!$this->toolCoordinator) {
            yield from $this->stream($messages);
            return;
        }

        try {
            // Get the latest user message safely
            $latestMessage = $messages[array_key_last($messages)] ?? ['content' => ''];
            $userMessage = $latestMessage['content'] ?? '';
            $userTimezone = $userContext['timezone'] ?? 'Asia/Makassar';

            // Check if message needs tool calling
            $toolRequest = $this->toolCoordinator->processMessage($userMessage, $userTimezone);

            if (!$toolRequest['needs_tool']) {
                // Normal chat without tools
                yield from $this->stream($messages);
                return;
            }

            // Execute tool first and get result (similar to OpenAI approach)
            $toolResult = $this->toolCoordinator->executeTool(
                $toolRequest['tool_name'],
                $toolRequest['arguments'],
                $toolRequest['timezone']
            );

            // Add tool result with natural response guidance - let LLM format naturally
            $toolData = $toolResult['tool_result']['data'] ?? $toolResult['tool_result'];
            $messages[] = [
                'role' => 'system',
                'content' => "Tool {$toolRequest['tool_name']} executed successfully with this data: " . json_encode($toolData) . ". Provide a natural Indonesian response based on this data."
            ];

            // Stream normal response with tool result in context
            yield from $this->stream($messages);

        } catch (\Exception $e) {
            Log::error('Bedrock streaming with tools error', [
                'message' => $e->getMessage(),
            ]);
            yield 'Error: Unable to generate response with tools.';
        }
    }

    public function stream(array $messages): \Generator
    {
        // Separate system messages from conversation
        $systemPrompt = $this->extractSystemPrompt($messages);
        $conversationMessages = $this->formatMessages($messages);

        // Build request payload for Claude
        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 4096,
            'messages' => $conversationMessages,
            'temperature' => 0.7,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        try {
            // Use direct HTTP streaming to bypass AWS SDK buffering
            yield from $this->streamWithGuzzle($payload);
        } catch (\Exception $e) {
            Log::error('Bedrock streaming error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Stream using Guzzle HTTP client with direct binary parsing.
     * This bypasses AWS SDK's EventParsingIterator which buffers responses.
     */
    protected function streamWithGuzzle(array $payload): \Generator
    {
        $region = config('llm.bedrock.region');
        $host = "bedrock-runtime.{$region}.amazonaws.com";
        $url = "https://{$host}/model/{$this->model}/invoke-with-response-stream";

        // Create HTTP request
        $httpClient = new Client(['stream' => true]);
        $body = json_encode($payload);
        $request = new Request('POST', $url, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $body);

        // Sign request with AWS Signature V4
        $credentials = new Credentials(
            config('llm.bedrock.key'),
            config('llm.bedrock.secret')
        );
        $signer = new SignatureV4('bedrock', $region);
        $signedRequest = $signer->signRequest($request, $credentials);

        // Send request and get stream
        $response = $httpClient->send($signedRequest, ['stream' => true]);
        $stream = $response->getBody();

        // Parse AWS binary event stream format
        while (! $stream->eof()) {
            // Read prelude (8 bytes: 4 for total length, 4 for headers length)
            $prelude = $stream->read(8);
            if (strlen($prelude) < 8) {
                break; // End of stream
            }

            // Parse big-endian integers
            $totalLength = unpack('N', substr($prelude, 0, 4))[1];
            $headersLength = unpack('N', substr($prelude, 4, 4))[1];

            // Read prelude CRC (4 bytes)
            $preludeCrc = $stream->read(4);

            // Read headers
            $headers = [];
            if ($headersLength > 0) {
                $headersData = $stream->read($headersLength);
                $headers = $this->parseEventHeaders($headersData);
            }

            // Calculate payload length
            $payloadLength = $totalLength - 4 - 4 - 4 - $headersLength - 4; // total - prelude - prelude_crc - headers - message_crc

            // Read payload
            $payload = '';
            if ($payloadLength > 0) {
                $payload = $stream->read($payloadLength);
            }

            // Read message CRC (4 bytes)
            $messageCrc = $stream->read(4);

            $eventType = $headers[':event-type'] ?? 'unknown';

            // Parse payload if it's a chunk event
            if ($eventType === 'chunk') {
                $chunk = json_decode($payload, true);

                // AWS returns base64-encoded bytes in the 'bytes' field
                if (isset($chunk['bytes'])) {
                    $decodedPayload = base64_decode($chunk['bytes']);
                    $chunk = json_decode($decodedPayload, true);
                }

                if ($chunk && isset($chunk['type']) && $chunk['type'] === 'content_block_delta' && isset($chunk['delta']['text'])) {
                    $text = $chunk['delta']['text'];
                    yield $text;
                    flush(); // Force immediate output
                }
            }
        }
    }

    /**
     * Parse AWS event stream headers.
     */
    protected function parseEventHeaders(string $headersData): array
    {
        $headers = [];
        $offset = 0;
        $length = strlen($headersData);

        while ($offset < $length) {
            // Read header name length (1 byte)
            $nameLength = ord($headersData[$offset]);
            $offset += 1;

            // Read header name
            $name = substr($headersData, $offset, $nameLength);
            $offset += $nameLength;

            // Read value type (1 byte)
            $valueType = ord($headersData[$offset]);
            $offset += 1;

            // Read value length (2 bytes, big-endian)
            $valueLength = unpack('n', substr($headersData, $offset, 2))[1];
            $offset += 2;

            // Read value
            $value = substr($headersData, $offset, $valueLength);
            $offset += $valueLength;

            $headers[$name] = $value;
        }

        return $headers;
    }

    public function generateTitle(string $firstMessage): string
    {
        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 50,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $firstMessage,
                ],
            ],
            'system' => 'Generate a concise, descriptive title (max 50 characters) for a chat that starts with the following message. Respond with only the title, no quotes or extra formatting.',
            'temperature' => 0.7,
        ];

        try {
            $response = $this->client->invokeModel([
                'modelId' => $this->titleModel,
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'body' => json_encode($payload),
            ]);

            $result = json_decode($response->get('body')->getContents(), true);

            // Extract text from Claude response
            if (isset($result['content'][0]['text'])) {
                return trim($result['content'][0]['text']);
            }

            return 'Untitled';
        } catch (AwsException $e) {
            Log::error('Bedrock title generation error', [
                'message' => $e->getMessage(),
                'code' => $e->getAwsErrorCode(),
            ]);

            return 'Untitled';
        }
    }

    public function getName(): string
    {
        return 'bedrock';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Extract system prompt from messages array.
     */
    protected function extractSystemPrompt(array $messages): ?string
    {
        foreach ($messages as $message) {
            $role = $message['role'] ?? $message['type'] ?? null;
            if ($role === 'system') {
                return $message['content'];
            }
        }

        return null;
    }

    /**
     * Format messages for Claude (remove system messages, ensure alternating user/assistant).
     */
    protected function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            // Get role from either 'role' or 'type' field
            $role = $message['role'] ?? $message['type'] ?? null;

            if (! $role) {
                continue;
            }

            // Skip system messages (handled separately)
            if ($role === 'system') {
                continue;
            }

            // Map 'prompt' to 'user' and 'response' to 'assistant'
            $mappedRole = match ($role) {
                'prompt', 'user' => 'user',
                'response', 'assistant' => 'assistant',
                default => $role,
            };

            $formatted[] = [
                'role' => $mappedRole,
                'content' => $message['content'],
            ];
        }

        return $formatted;
    }

    /**
     * Convert OpenAI function schemas to Claude tool format.
     */
    protected function convertToClaudeTools(array $openAIFunctionSchemas): array
    {
        $claudeTools = [];

        foreach ($openAIFunctionSchemas as $schema) {
            $function = $schema['function'];
            
            // Convert parameters to Claude format
            $claudeInputSchema = [
                'type' => 'object',
                'properties' => [],
                'required' => []
            ];

            if (isset($function['parameters']['properties'])) {
                foreach ($function['parameters']['properties'] as $name => $prop) {
                    $claudeInputSchema['properties'][$name] = [
                        'type' => $prop['type'] ?? 'string',
                        'description' => $prop['description'] ?? '',
                    ];

                    if (isset($prop['default'])) {
                        $claudeInputSchema['properties'][$name]['default'] = $prop['default'];
                    }
                }
            }

            if (isset($function['parameters']['required'])) {
                $claudeInputSchema['required'] = $function['parameters']['required'];
            }

            $claudeTools[] = [
                'name' => $function['name'],
                'description' => $function['description'] ?? '',
                'input_schema' => $claudeInputSchema
            ];
        }

        return $claudeTools;
    }




}
