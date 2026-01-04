<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpClient
{
    private string $baseUrl;
    private ?array $cachedTools = null;

    public function __construct()
    {
        $this->baseUrl = config('app.url') . '/mcp/datetime';
    }

    /**
     * Get all available tools from MCP server (dynamic discovery).
     */
    public function getAvailableTools(): array
    {
        if ($this->cachedTools !== null) {
            return $this->cachedTools;
        }

        try {
            $response = Http::timeout(10)->post($this->baseUrl, [
                'jsonrpc' => '2.0',
                'id' => now()->timestamp,
                'method' => 'tools/list',
                'params' => []
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $this->cachedTools = $result['result']['tools'] ?? [];
                return $this->cachedTools;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to discover MCP tools', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Convert MCP tool schema to OpenAI function schema format.
     */
    public function convertToOpenAIFunctionSchema(array $mcpTool): array
    {
        $inputSchema = $mcpTool['inputSchema'] ?? [];
        $properties = $inputSchema['properties'] ?? [];
        $required = $inputSchema['required'] ?? [];

        // Convert MCP properties to OpenAI format
        $openaiProperties = [];
        if (is_array($properties)) {
            foreach ($properties as $name => $property) {
                if (!is_array($property)) continue;

                $openaiProperties[$name] = [
                    'type' => $property['type'] ?? 'string',
                    'description' => $property['description'] ?? '',
                ];

                // Handle defaults
                if (isset($property['default'])) {
                    $openaiProperties[$name]['default'] = $property['default'];
                }

                // Handle enums
                if (isset($property['enum']) && is_array($property['enum'])) {
                    $openaiProperties[$name]['enum'] = $property['enum'];
                }
            }
        }

        // Convert tool name: remove '-tool' suffix and convert to snake_case
        $functionName = $this->convertToOpenAIFunctionName($mcpTool['name']);



        return [
            'type' => 'function',
            'function' => [
                'name' => $functionName,
                'description' => $mcpTool['description'] ?? '',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $openaiProperties,
                    ...(count($required) > 0 ? ['required' => $required] : []),
                ]
            ]
        ];
    }

    /**
     * Convert MCP tool name to OpenAI function name format.
     */
    private function convertToOpenAIFunctionName(string $mcpToolName): string
    {
        // Remove '-tool' suffix
        $name = preg_replace('/-tool$/', '', $mcpToolName);

        // Convert kebab-case to snake_case
        $name = str_replace('-', '_', $name);

        return $name;
    }

    /**
     * Get reverse mapping from OpenAI function name back to MCP tool name.
     */
    public function getMcpToolNameFromFunction(string $functionName): string
    {
        // Convert snake_case back to kebab-case and add '-tool' suffix
        $mcpName = str_replace('_', '-', $functionName);

        // Only add -tool suffix if it doesn't already have it
        if (!str_ends_with($mcpName, '-tool')) {
            $mcpName .= '-tool';
        }

        return $mcpName;
    }

    /**
     * Call an MCP tool via HTTP.
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        try {
            $response = Http::timeout(10)->post($this->baseUrl, [
                'jsonrpc' => '2.0',
                'id' => now()->timestamp,
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => $arguments
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception("HTTP request failed: {$response->status()}");
            }

            $data = $response->json();

            if (!isset($data['result']['content'][0]['text'])) {
                throw new \Exception('Invalid MCP response format');
            }

            // Parse the JSON text content from MCP response
            $textContent = $data['result']['content'][0]['text'];
            $result = json_decode($textContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse MCP tool result: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $result,
                'tool' => $toolName,
                'arguments' => $arguments
            ];

        } catch (\Exception $e) {
            Log::error('MCP tool call failed', [
                'tool' => $toolName,
                'arguments' => $arguments,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool' => $toolName,
                'arguments' => $arguments
            ];
        }
    }

    /**
     * Get current datetime for a specific timezone.
     */
    public function getCurrentDateTime(?string $timezone = null): array
    {
        $arguments = $timezone ? ['timezone' => $timezone] : [];
        return $this->callTool('get-current-date-time-tool', $arguments);
    }

    /**
     * Get timezone information.
     */
    public function getTimezoneInfo(?string $timezone = null): array
    {
        $arguments = $timezone ? ['timezone' => $timezone] : [];
        return $this->callTool('get-timezone-info-tool', $arguments);
    }

    /**
     * Convert timezone.
     */
    public function convertTimezone(string $datetime, string $fromTimezone, string $toTimezone): array
    {
        return $this->callTool('convert-timezone-tool', [
            'datetime' => $datetime,
            'from_timezone' => $fromTimezone,
            'to_timezone' => $toTimezone
        ]);
    }

    /**
     * List available timezones.
     */
    public function listTimezones(int $limit = 50): array
    {
        return $this->callTool('list-timezones-tool', ['limit' => $limit]);
    }
}