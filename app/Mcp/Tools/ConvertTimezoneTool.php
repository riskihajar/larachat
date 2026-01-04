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
class ConvertTimezoneTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Convert a datetime from one timezone to another. Supports datetime string, source timezone, and target timezone parameters.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $datetime = $request->get('datetime') ?? 'now';
        $fromTimezone = $request->get('from_timezone') ?? 'Asia/Makassar';
        $toTimezone = $request->get('to_timezone') ?? 'UTC';

        try {
            $date = new \DateTime($datetime, new \DateTimeZone($fromTimezone));
            $date->setTimezone(new \DateTimeZone($toTimezone));

            $response = [
                'input' => [
                    'datetime' => $datetime,
                    'from_timezone' => $fromTimezone,
                    'to_timezone' => $toTimezone,
                ],
                'original' => [
                    'datetime' => $date->format('Y-m-d H:i:s'),
                    'timezone' => $fromTimezone,
                    'offset' => $date->getOffset(),
                    'abbr' => $date->format('T'),
                    'iso_8601' => $date->format('c'),
                ],
                'converted' => [
                    'datetime' => $date->format('Y-m-d H:i:s'),
                    'timezone' => $toTimezone,
                    'offset' => $date->getOffset(),
                    'abbr' => $date->format('T'),
                    'iso_8601' => $date->format('c'),
                ],
                'conversion_info' => [
                    'timezone_difference' => $date->getOffset() / 3600, // hours
                    'dst_affected' => $date->format('I') == '1',
                    'successful' => true,
                ],
            ];

            return Response::json($response);
        } catch (\Exception $e) {
            return Response::text('Error: Invalid timezone or datetime provided. Error: ' . $e->getMessage());
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
            'datetime' => $schema->string()
                ->description('The datetime string to convert. If not provided, uses current time.')
                ->default('now'),
            'from_timezone' => $schema->string()
                ->description('Source timezone identifier (e.g., "UTC", "Asia/Makassar"). If not provided, defaults to Asia/Makassar timezone.')
                ->default('Asia/Makassar'),
            'to_timezone' => $schema->string()
                ->description('Target timezone identifier (e.g., "Asia/Makassar", "America/New_York"). Required for conversion.')
                ->default('UTC'),
        ];
    }
}