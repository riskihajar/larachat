<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Services\LLM\Contracts\LLMProviderInterface;
use OpenAI\Laravel\Facades\OpenAI;

class ToolCoordinator
{
    private McpClient $mcpClient;
    private DateTimeFormatter $formatter;
    private LLMProviderInterface $llmProvider;

    public function __construct(LLMProviderInterface $llmProvider)
    {
        $this->llmProvider = $llmProvider;
        $this->mcpClient = new McpClient();
        $this->formatter = new DateTimeFormatter();
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
     */
    public function executeTool(string $functionName, array $arguments, string $timezone = 'Asia/Makassar'): array
    {
        // Convert OpenAI function name back to MCP tool name
        $mcpToolName = $this->mcpClient->getMcpToolNameFromFunction($functionName);

        $result = $this->mcpClient->callTool($mcpToolName, $arguments);
        $formattedResponse = $this->formatter->formatToolResult($mcpToolName, $result, $timezone);

        return [
            'success' => $result['success'],
            'tool_result' => $result,
            'formatted_response' => $formattedResponse,
            'tool_name' => $mcpToolName,
            'arguments' => $arguments
        ];
    }

    /**
     * Process a user message and determine if tool calling is needed.
     */
    public function processMessage(string $message, string $userTimezone = 'Asia/Makassar'): array
    {
        // Check if message is a datetime query using LLM reasoning
        if (!$this->formatter->isDateTimeQuery($message)) {
            return [
                'needs_tool' => false,
                'message' => $message
            ];
        }

        // Extract timezone from message if mentioned
        $extractedTimezone = $this->formatter->extractTimezoneFromMessage($message);
        $targetTimezone = $extractedTimezone ?? $userTimezone;

        // Use LLM to intelligently select the best tool
        try {
            $toolSelection = $this->llmSelectTool($message, $targetTimezone);
            $toolName = $toolSelection['tool_name'];
            $arguments = $this->buildArguments($toolName, $message, $targetTimezone);

            return [
                'needs_tool' => true,
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'timezone' => $targetTimezone,
                'message' => $message,
                'selection_reason' => $toolSelection['reason'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('LLM tool selection failed', [
                'message' => $e->getMessage(),
                'query' => $message
            ]);
            
            // If LLM fails, we cannot proceed - no tools available
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
            // For Bedrock, we'd need to implement the AWS Bedrock API call
            // For now, let's fallback to OpenAI if available, otherwise throw error
            if (config('openai.api_key')) {
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
            } else {
                throw new \Exception('No LLM provider available for tool selection');
            }
        } else {
            throw new \Exception("Unsupported LLM provider: {$providerName}");
        }
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
     * This is now a placeholder - in a real implementation, use AI to extract
     * parameters from natural language.
     */
    private function buildArguments(string $toolName, string $message, string $timezone): array
    {
        // For now, just return basic timezone argument
        // In production, use AI to extract relevant parameters from the message
        return ['timezone' => $timezone];
    }
}