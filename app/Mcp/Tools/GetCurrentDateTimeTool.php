<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCurrentDateTimeTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Gets complete current datetime with timezone information. Supports optional timezone parameter to get time in specific timezone.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // Get timezone from parameters, default to Makassar timezone
        $requestedTimezone = $request->get('timezone');
        $defaultTimezone = 'Asia/Makassar';
        $targetTimezone = $requestedTimezone ?? $defaultTimezone;

        try {
            // Create now instance in requested timezone
            $now = now()->timezone($targetTimezone);
            $timezone = $now->timezone;

            $response = [
                'datetime' => $now->format('Y-m-d H:i:s T'),
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i:s'),
                'timestamp' => $now->timestamp,
                'timezone' => [
                    'name' => $timezone->getName(),
                    'offset' => $timezone->getOffset($now),
                    'abbr' => $now->format('T'),
                ],
                'iso_8601' => $now->toISOString(),
                'requested_timezone' => $targetTimezone,
                'server_timezone' => $defaultTimezone,
            ];

            return Response::json($response);
        } catch (\Exception $e) {
            return Response::text('Error: Invalid timezone provided. Error: ' . $e->getMessage());
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('Optional timezone identifier (e.g., "Asia/Makassar", "UTC", "America/New_York"). If not provided, defaults to Asia/Makassar timezone.'),
        ];
    }
}