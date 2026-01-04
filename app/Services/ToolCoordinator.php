<?php

declare(strict_types=1);

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class ToolCoordinator
{
    private McpClient $mcpClient;
    private DateTimeFormatter $formatter;

    public function __construct()
    {
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
    }

    /**
     * Use LLM to intelligently select the best tool based on user message and tool descriptions.
     */
    private function llmSelectTool(string $message, string $userTimezone): array
    {
        // Get available tools
        $availableTools = $this->mcpClient->getAvailableTools();
        
        if (empty($availableTools)) {
            // Fallback to default tool
            return [
                'tool_name' => 'get-current-date-time-tool',
                'reason' => 'No tools available, using default'
            ];
        }

        // If OpenAI is not available, fallback to basic logic
        if (!$this->isOpenAIAvailable()) {
            return $this->basicToolSelection($message);
        }

        try {
            // Prepare tool descriptions for LLM
            $toolDescriptions = [];
            foreach ($availableTools as $tool) {
                $toolDescriptions[] = "- {$tool['name']}: {$tool['description']}";
            }

            // Ask LLM to select the best tool
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a tool selection assistant. Based on the user message and available tools, select the most appropriate tool. Available tools: ' . implode("\n", $toolDescriptions) . '. Respond with only the tool name (e.g., "get-current-date-time-tool").'
                    ],
                    [
                        'role' => 'user',
                        'content' => "User message: \"{$message}\"\nUser timezone: {$userTimezone}\n\nWhich tool should be used? Respond with only the tool name."
                    ]
                ],
                'max_tokens' => 50,
                'temperature' => 0
            ]);

            $selectedTool = trim(strtolower($response->choices[0]->message->content));
            
            // Validate the selected tool exists
            $validToolNames = array_column($availableTools, 'name');
            if (in_array($selectedTool, $validToolNames)) {
                Log::debug('LLM selected tool', [
                    'message' => $message,
                    'selected_tool' => $selectedTool
                ]);
                
                return [
                    'tool_name' => $selectedTool,
                    'reason' => 'LLM intelligent selection'
                ];
            }
            
        } catch (\Exception $e) {
            Log::warning('LLM tool selection failed, falling back to basic logic', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
        }

        // Fallback to basic selection
        return $this->basicToolSelection($message);
    }

    /**
     * Basic tool selection as fallback (using simple patterns).
     */
    private function basicToolSelection(string $message): array
    {
        $messageLower = strtolower($message);

        // Timezone conversion patterns
        if (preg_match('/(convert|konversi|ubah|ke|from)/', $messageLower)) {
            return [
                'tool_name' => 'convert-timezone-tool',
                'reason' => 'Pattern match: conversion keywords'
            ];
        }

        // Timezone info patterns  
        if (preg_match('/(info|timezone|zona waktu)/', $messageLower)) {
            return [
                'tool_name' => 'get-timezone-info-tool',
                'reason' => 'Pattern match: info keywords'
            ];
        }

        // List timezones patterns
        if (preg_match('/(list|daftar|semua)/', $messageLower)) {
            return [
                'tool_name' => 'list-timezones-tool',
                'reason' => 'Pattern match: list keywords'
            ];
        }

        // Default to current datetime
        return [
            'tool_name' => 'get-current-date-time-tool',
            'reason' => 'Default selection'
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