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
        $timezone = Date::now()->timezone;

        $response = [
            'app_timezone' => [
                'name' => $timezone->getName(),
                'offset' => $timezone->getOffset(Date::now()),
                'abbr' => Date::now()->format('T'),
            ],
            'config' => [
                'timezone' => config('app.timezone'),
            ],
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