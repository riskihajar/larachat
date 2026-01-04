<?php

declare(strict_types=1);

namespace App\Services;

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
        // Check if message is a datetime query
        if (!$this->formatter->isDateTimeQuery($message)) {
            return [
                'needs_tool' => false,
                'message' => $message
            ];
        }

        // Extract timezone from message if mentioned
        $extractedTimezone = $this->formatter->extractTimezoneFromMessage($message);
        $targetTimezone = $extractedTimezone ?? $userTimezone;

        // Determine which tool to call based on message content
        $toolName = $this->determineTool($message);
        $arguments = $this->buildArguments($toolName, $message, $targetTimezone);

        return [
            'needs_tool' => true,
            'tool_name' => $toolName,
            'arguments' => $arguments,
            'timezone' => $targetTimezone,
            'message' => $message
        ];
    }

    /**
     * Determine which tool to use based on message content.
     * This is now more intelligent - matches user intent to available tools.
     */
    private function determineTool(string $message): string
    {
        $messageLower = strtolower($message);
        $availableTools = $this->mcpClient->getAvailableTools();

        // Current datetime queries
        if (preg_match('/(sekarang|sekarang jam|jam berapa|waktu sekarang|hari ini|tanggal)/', $messageLower)) {
            return 'get-current-date-time-tool';
        }

        // Timezone conversion queries
        if (preg_match('/(konversi|convert|ubah|ubah dari|ke zona)/', $messageLower)) {
            return 'convert-timezone-tool';
        }

        // Timezone info queries
        if (preg_match('/(zona waktu|timezone|info)/', $messageLower)) {
            return 'get-timezone-info-tool';
        }

        // Default to current datetime tool
        return 'get-current-date-time-tool';
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