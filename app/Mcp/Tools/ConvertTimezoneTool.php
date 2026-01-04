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
    protected string $description = 'Convert a datetime from one timezone to another';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $datetime = $request->get('datetime') ?? 'now';
        $fromTimezone = $request->get('from_timezone') ?? config('app.timezone');
        $toTimezone = $request->get('to_timezone') ?? 'UTC';

        try {
            $date = new \DateTime($datetime, new \DateTimeZone($fromTimezone));
            $date->setTimezone(new \DateTimeZone($toTimezone));

            $response = [
                'original' => [
                    'datetime' => $date->format('Y-m-d H:i:s'),
                    'timezone' => $fromTimezone,
                ],
                'converted' => [
                    'datetime' => $date->format('Y-m-d H:i:s'),
                    'timezone' => $toTimezone,
                    'offset' => $date->getOffset(),
                    'iso_8601' => $date->format('c'),
                ],
            ];

            return Response::json($response);
        } catch (\Exception $e) {
            return Response::text('Error: ' . $e->getMessage());
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}