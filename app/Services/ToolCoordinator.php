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
     * Execute a tool and return formatted response.
     */
    public function executeTool(string $toolName, array $arguments, string $timezone = 'Asia/Makassar'): array
    {
        $result = $this->mcpClient->callTool($toolName, $arguments);
        $formattedResponse = $this->formatter->formatToolResult($toolName, $result, $timezone);

        return [
            'success' => $result['success'],
            'tool_result' => $result,
            'formatted_response' => $formattedResponse,
            'tool_name' => $toolName,
            'arguments' => $arguments
        ];
    }

    /**
     * Determine which tool to use based on message content.
     */
    private function determineTool(string $message): string
    {
        $messageLower = strtolower($message);

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

        // List timezones queries
        if (preg_match('/(daftar|list|semua timezone)/', $messageLower)) {
            return 'list-timezones-tool';
        }

        // Default to current datetime
        return 'get-current-date-time-tool';
    }

    /**
     * Build arguments for the tool based on message content.
     */
    private function buildArguments(string $toolName, string $message, string $timezone): array
    {
        $arguments = [];

        switch ($toolName) {
            case 'get-current-date-time-tool':
                $arguments['timezone'] = $timezone;
                break;

            case 'get-timezone-info-tool':
                $arguments['timezone'] = $timezone;
                break;

            case 'convert-timezone-tool':
                // Extract datetime, from_timezone, and to_timezone from message
                $arguments = $this->extractConversionArguments($message, $timezone);
                break;

            case 'list-timezones-tool':
                $arguments['limit'] = 10; // Limit for conversational responses
                break;
        }

        return $arguments;
    }

    /**
     * Extract arguments for timezone conversion from message.
     */
    private function extractConversionArguments(string $message, string $defaultTimezone): array
    {
        $arguments = [
            'datetime' => 'now',
            'from_timezone' => $defaultTimezone,
            'to_timezone' => 'UTC' // Default target
        ];

        $messageLower = strtolower($message);

        // Common timezone patterns
        $timezoneMap = [
            'makassar' => 'Asia/Makassar',
            'jakarta' => 'Asia/Jakarta',
            'utc' => 'UTC',
            'new york' => 'America/New_York',
            'london' => 'Europe/London',
            'tokyo' => 'Asia/Tokyo',
            'singapore' => 'Asia/Singapore'
        ];

        // Extract "from" timezone
        if (preg_match('/dari\s+([a-z\s]+?)(?:\s+ke|\s+kezona|\s+timezone|$)/i', $message, $matches)) {
            $fromTzName = trim($matches[1]);
            $arguments['from_timezone'] = $this->mapTimezoneName($fromTzName, $timezoneMap) ?? $defaultTimezone;
        }

        // Extract "to" timezone
        if (preg_match('/ke\s+([a-z\s]+?)(?:\s+timezone|$)/i', $message, $matches)) {
            $toTzName = trim($matches[1]);
            $arguments['to_timezone'] = $this->mapTimezoneName($toTzName, $timezoneMap) ?? 'UTC';
        }

        // Extract specific datetime if mentioned
        if (preg_match('/(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|hari ini|sekarang|now)/i', $message, $matches)) {
            $datetimeStr = strtolower($matches[1]);
            $arguments['datetime'] = match ($datetimeStr) {
                'hari ini', 'sekarang', 'now' => 'now',
                default => $matches[1]
            };
        }

        return $arguments;
    }

    /**
     * Map common timezone names to proper timezone identifiers.
     */
    private function mapTimezoneName(string $name, array $timezoneMap): ?string
    {
        $nameLower = strtolower($name);
        
        foreach ($timezoneMap as $pattern => $timezone) {
            if (str_contains($nameLower, $pattern)) {
                return $timezone;
            }
        }

        // Check if the name itself is a valid timezone
        try {
            new \DateTimeZone($name);
            return $name;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get function schemas for OpenAI function calling.
     */
    public function getFunctionSchemas(): array
    {
        return [
            [
                'name' => 'get_current_datetime',
                'description' => 'Mendapatkan tanggal dan waktu saat ini dengan informasi timezone. Gunakan ketika user menanyakan waktu sekarang, tanggal hari ini, atau jam berapa sekarang.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone identifier (contoh: "Asia/Makassar", "UTC", "America/New_York"). Default ke Asia/Makassar untuk user Indonesia.',
                            'default' => 'Asia/Makassar'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'convert_timezone',
                'description' => 'Mengkonversi waktu dari satu timezone ke timezone lain. Gunakan ketika user ingin tahu waktu di timezone lain atau ingin konversi waktu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'datetime' => [
                            'type' => 'string',
                            'description' => 'Tanggal dan waktu untuk dikonversi. Format: "2024-01-15 14:30:00" atau "now" untuk waktu saat ini.',
                            'default' => 'now'
                        ],
                        'from_timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone asal. Contoh: "Asia/Makassar", "UTC". Default ke timezone user.',
                            'default' => 'Asia/Makassar'
                        ],
                        'to_timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone tujuan. Contoh: "America/New_York", "Europe/London".',
                        ]
                    ],
                    'required' => ['to_timezone']
                ]
            ],
            [
                'name' => 'get_timezone_info',
                'description' => 'Mendapatkan informasi detail tentang timezone tertentu. Gunakan ketika user menanyakan info zona waktu atau offset timezone.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone identifier untuk dicek. Contoh: "Asia/Makassar", "UTC".',
                        ]
                    ],
                    'required' => ['timezone']
                ]
            ],
            [
                'name' => 'list_timezones',
                'description' => 'Mendapatkan daftar timezone yang tersedia. Gunakan ketika user ingin melihat daftar zona waktu yang bisa digunakan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Jumlah timezone yang akan dikembalikan. Default 10 untuk response yang mudah dibaca.',
                            'default' => 10,
                            'maximum' => 50
                        ]
                    ]
                ]
            ]
        ];
    }
}