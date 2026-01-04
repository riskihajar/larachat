<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTimezonesTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List all available timezone identifiers';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $timezones = \DateTimeZone::listIdentifiers();

        $response = [
            'total_timezones' => count($timezones),
            'timezones' => $timezones,
        ];

        return Response::json($response);
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
