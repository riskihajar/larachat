<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Services\LLM\Contracts\LLMProviderInterface;
use OpenAI\Laravel\Facades\OpenAI;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;

class ToolCoordinator
{
    private McpClient $mcpClient;
    private LLMProviderInterface $llmProvider;

    public function __construct(LLMProviderInterface $llmProvider)
    {
        $this->llmProvider = $llmProvider;
        $this->mcpClient = new McpClient();
    }

    /**
     * Get function schemas for OpenAI function calling - DYNAMIC DISCOVERY!
     */
    public function getFunctionSchemas(): array
    {
        $mcpTools = $this->mcpClient->getAvailableTools();
        $openAISchemas = [];

        foreach ($mcpTools as $mcpTool) {
            // Skip tools with no properties for now (like list-timezones-tool)
            // In production, you'd handle these differently
            $properties = $mcpTool['inputSchema']['properties'] ?? [];
            if (empty($properties)) {
                continue;
            }

            $openAISchemas[] = $this->mcpClient->convertToOpenAIFunctionSchema($mcpTool);
        }

        return $openAISchemas;
    }

    /**
     * Execute tool using dynamic MCP tool name resolution.
     * Returns raw tool results for LLM to format naturally.
     */
    public function executeTool(string $functionName, array $arguments, string $timezone = 'Asia/Makassar'): array
    {
        // Convert OpenAI function name back to MCP tool name
        $mcpToolName = $this->mcpClient->getMcpToolNameFromFunction($functionName);

        $result = $this->mcpClient->callTool($mcpToolName, $arguments);

        return [
            'success' => $result['success'],
            'tool_result' => $result,
            'tool_name' => $mcpToolName,
            'arguments' => $arguments
        ];
    }

    /**
     * Process a user message and determine if tool calling is needed.
     * Let LLM handle all classification and tool selection naturally.
     */
    public function processMessage(string $message, string $userTimezone = 'Asia/Makassar'): array
    {
        // First, check if this is clearly a datetime-related query
        // Only proceed to LLM tool selection if message is datetime-related
        $datetimeKeywords = [
            // Indonesian
            'jam berapa', 'tanggal berapa', 'hari apa', 'sekarang jam', 'sekarang tanggal',
            'waktu di', 'zona waktu', 'konversi waktu', 'ubah waktu', 'timezone di',
            'jam di', 'tanggal di', 'hari di', 'wita', 'wit', 'utc',
            // English
            'what time', 'what date', 'current time', 'time in', 'date in',
            'timezone', 'convert time', 'time conversion', 'time now',
            // Generic keywords
            'jam', 'waktu', 'tanggal', 'hari', 'sekarang', 'time', 'date'
        ];

        $isDatetimeRelated = false;
        $lowerMessage = strtolower($message);
        foreach ($datetimeKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                $isDatetimeRelated = true;
                break;
            }
        }

        // If message is NOT datetime-related, skip tool selection entirely
        if (!$isDatetimeRelated) {
            return [
                'needs_tool' => false,
                'message' => $message,
                'reason' => 'Message does not appear to be datetime-related'
            ];
        }

        // Now let LLM handle the specific tool selection and parameter extraction
        try {
            $toolSelection = $this->llmSelectTool($message, $userTimezone);
            $toolName = $toolSelection['tool_name'];
            $arguments = $this->buildArguments($toolName, $message, $userTimezone);

            return [
                'needs_tool' => true,
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'timezone' => $userTimezone,
                'message' => $message,
                'selection_reason' => $toolSelection['reason'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('LLM tool selection failed', [
                'message' => $e->getMessage(),
                'query' => $message
            ]);

            // If LLM fails, return no tool needed - let normal chat continue
            return [
                'needs_tool' => false,
                'message' => $message,
                'error' => 'Tool selection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pure LLM-based tool selection - no manual patterns!
     * LLM reads tools directly from MCP server.
     */
    private function llmSelectTool(string $message, string $userTimezone): array
    {
        // Get tools directly from MCP server
        $availableTools = $this->mcpClient->getAvailableTools();
        
        if (empty($availableTools)) {
            throw new \Exception('No MCP tools available');
        }

        // If OpenAI is not available, we cannot proceed (no fallback)
        if (!$this->isOpenAIAvailable()) {
            throw new \Exception('OpenAI not available for tool selection');
        }

        // Prepare tool descriptions for LLM to read directly from MCP
        $toolDescriptions = [];
        foreach ($availableTools as $tool) {
            $inputSchema = $tool['inputSchema'] ?? [];
            $properties = $inputSchema['properties'] ?? [];
            $params = [];
            
            foreach ($properties as $paramName => $paramInfo) {
                $params[] = "- {$paramName}: {$paramInfo['description']}";
            }
            
            $paramString = !empty($params) ? " (Parameters: " . implode(", ", $params) . ")" : "";
            $toolDescriptions[] = "- {$tool['name']}: {$tool['description']}{$paramString}";
        }

        // LLM reads tool descriptions and selects best match using the injected provider
        $prompt = "You are a pure AI tool selection assistant. Based ONLY on the user message and the available tools below, select the most appropriate tool. Do NOT use any external knowledge or assumptions. Only use the tool descriptions provided. Respond with only the exact tool name from the list.\n\nAvailable tools:\n" . implode("\n", $toolDescriptions) . "\n\nUser message: \"{$message}\"\nUser timezone: {$userTimezone}\n\nSelect the best tool. Respond with ONLY the tool name.";

        $response = $this->callLLMForToolSelection($prompt);

        $selectedTool = trim(strtolower($response->choices[0]->message->content));
        
        // Validate the selected tool exists
        $validToolNames = array_column($availableTools, 'name');
        if (!in_array($selectedTool, $validToolNames)) {
            throw new \Exception("LLM selected invalid tool: {$selectedTool}");
        }
        
        Log::info('LLM pure tool selection', [
            'message' => $message,
            'selected_tool' => $selectedTool,
            'available_tools' => count($availableTools),
            'provider_used' => $this->llmProvider->getName()
        ]);
        
        return [
            'tool_name' => $selectedTool,
            'reason' => 'Pure LLM selection from MCP tools'
        ];
    }

    /**
     * Call the appropriate LLM provider for tool selection.
     */
    private function callLLMForToolSelection(string $prompt): object
    {
        $providerName = $this->llmProvider->getName();

        if ($providerName === 'openai') {
            // Use OpenAI API
            return OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0
            ]);
        } elseif ($providerName === 'bedrock') {
            // Use Bedrock API for tool selection
            return $this->callBedrockForToolSelection($prompt);
        } else {
            throw new \Exception("Unsupported LLM provider: {$providerName}");
        }
    }

    /**
     * Call Bedrock API for tool selection.
     */
    private function callBedrockForToolSelection(string $prompt): object
    {
        $region = config('llm.bedrock.region');
        $model = 'us.anthropic.claude-3-5-haiku-20241022-v1:0';
        $host = "bedrock-runtime.{$region}.amazonaws.com";
        $url = "https://{$host}/model/{$model}/invoke";

        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 50,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0,
        ];

        // Create HTTP request
        $httpClient = new Client();
        $body = json_encode($payload);
        $request = new \GuzzleHttp\Psr7\Request('POST', $url, [
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

        // Send request
        $response = $httpClient->send($signedRequest);
        $responseBody = json_decode($response->getBody()->getContents(), true);

        // Convert Bedrock response to OpenAI-like format
        return (object) [
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => $responseBody['content'][0]['text'] ?? ''
                    ]
                ]
            ]
        ];
    }

    /**
     * Check if OpenAI API is available.
     */
    private function isOpenAIAvailable(): bool
    {
        return !app()->environment('testing') && 
               config('openai.api_key') && 
               !empty(config('openai.api_key'));
    }

    /**
     * Build arguments for the tool based on message content.
     * Extract parameters from natural language using LLM.
     */
    private function buildArguments(string $toolName, string $message, string $timezone): array
    {
        // Use LLM to extract relevant parameters from the message
        try {
            $prompt = "Extract parameters for the tool '{$toolName}' from this user message: \"{$message}\"

Return a JSON object with the parameters. Available parameter: timezone (default: {$timezone})

If the message mentions a specific location/timezone, extract it. Otherwise use the default timezone.

Respond with only valid JSON.";

            $response = $this->callLLMForToolSelection($prompt);
            $content = trim($response->choices[0]->message->content);

            // Try to parse JSON response
            $arguments = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($arguments)) {
                // Ensure timezone is always present
                if (!isset($arguments['timezone'])) {
                    $arguments['timezone'] = $timezone;
                }
                return $arguments;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to extract parameters from message', [
                'tool' => $toolName,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to basic timezone argument
        return ['timezone' => $timezone];
    }
}