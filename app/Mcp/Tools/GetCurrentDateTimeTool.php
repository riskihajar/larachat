<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

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
    public function description(): string
    {
        return 'Gets complete current datetime with timezone information';
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $now = now();
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
        ];

        return Response::json($response);
    }
}
