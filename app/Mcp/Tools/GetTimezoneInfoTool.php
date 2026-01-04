<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTimezoneInfoTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Gets the application\'s timezone configuration and information';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $requestedTimezone = $request->get('timezone');
        $defaultTimezone = 'Asia/Makassar';
        $targetTimezone = $requestedTimezone ?? $defaultTimezone;

        try {
            $now = Date::now()->timezone($targetTimezone);
            $timezone = $now->timezone;

            $response = [
                'requested_timezone' => $targetTimezone,
                'server_timezone' => $defaultTimezone,
                'timezone_info' => [
                    'name' => $timezone->getName(),
                    'offset' => $timezone->getOffset($now),
                    'abbr' => $now->format('T'),
                    'dst' => $now->isDST(),
                ],
                'config' => [
                    'timezone' => $defaultTimezone,
                ],
                'current_time' => [
                    'datetime' => $now->format('Y-m-d H:i:s T'),
                    'date' => $now->format('Y-m-d'),
                    'time' => $now->format('H:i:s'),
                    'iso_8601' => $now->toISOString(),
                ],
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