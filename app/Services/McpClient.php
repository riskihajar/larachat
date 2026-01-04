<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('app.url') . '/mcp/datetime';
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