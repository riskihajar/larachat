# MCP DateTime Server Implementation Guide

## Overview

This guide provides complete implementation details for creating a Laravel MCP server with DateTime tools.

## Prerequisites

- Laravel MCP package: `composer require laravel/mcp`
- Publish routes: `php artisan vendor:publish --tag=ai-routes`

## 1. Create MCP Server

### File: `app/Mcp/Servers/DateTimeServer.php`

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class DateTimeServer extends Server
{
    protected string $name = 'DateTime Server';
    protected string $version = '1.0.0';
    public string $instructions = 'This server provides tools for getting current date, time, timezone information, and converting time between different timezones. Use these tools when you need to provide datetime context or perform timezone conversions.';

    public array $tools = [
        \App\Mcp\Tools\GetCurrentDateTimeTool::class,
        \App\Mcp\Tools\GetTimezoneInfoTool::class,
        \App\Mcp\Tools\ConvertTimezoneTool::class,
        \App\Mcp\Tools\ListTimezonesTool::class,
    ];

    public array $resources = [];
    public array $prompts = [];
}
```

## 2. Create Tools

### A. Get Current DateTime Tool

**File: `app/Mcp/Tools/GetCurrentDateTimeTool.php`**

```php
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
    public function description(): string
    {
        return 'Gets complete current datetime with timezone information';
    }

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
```

### B. Get Timezone Info Tool

**File: `app/Mcp/Tools/GetTimezoneInfoTool.php`**

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTimezoneInfoTool extends Tool
{
    public function description(): string
    {
        return 'Gets information about the application timezone';
    }

    public function handle(Request $request): Response
    {
        $appTimezone = config('app.timezone');
        $tz = new \DateTimeZone($appTimezone);
        $now = new \DateTime('now', $tz);

        $response = [
            'current_timezone' => [
                'name' => $appTimezone,
                'offset' => $tz->getOffset($now) / 3600,
                'offset_human' => sprintf('%+03d:%02d',
                    $tz->getOffset($now) / 3600,
                    ($tz->getOffset($now) % 3600) / 60
                ),
                'abbr' => $now->format('T'),
            ],
        ];

        return Response::json($response);
    }
}
```

### C. Convert Timezone Tool

**File: `app/Mcp/Tools/ConvertTimezoneTool.php`**

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ConvertTimezoneTool extends Tool
{
    public function description(): string
    {
        return 'Converts time between different timezones';
    }

    public function handle(Request $request): Response
    {
        $sourceTz = $request->get('source_timezone') ?? config('app.timezone');
        $targetTzName = $request->get('target_timezone');

        if (!$targetTzName) {
            return Response::error('target_timezone is required');
        }

        $targetTz = new \DateTimeZone($targetTzName);

        // Create source datetime
        $sourceDatetime = new \DateTime('now', new \DateTimeZone($sourceTz));

        // Convert to target timezone
        $sourceDatetime->setTimezone($targetTz);

        $response = [
            'original' => [
                'datetime' => $sourceDatetime->format('Y-m-d H:i:s T'),
                'timezone' => $sourceTz,
            ],
            'converted' => [
                'datetime' => $sourceDatetime->format('Y-m-d H:i:s T'),
                'timezone' => $targetTzName,
                'utc_offset' => $targetTz->getOffset($sourceDatetime) / 3600,
            ],
            'iso_8601' => $sourceDatetime->format('c'),
        ];

        return Response::json($response);
    }
}
```

### D. List Timezones Tool

**File: `app/Mcp/Tools/ListTimezonesTool.php`**

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTimezonesTool extends Tool
{
    public function description(): string
    {
        return 'Lists all available timezones';
    }

    public function handle(Request $request): Response
    {
        $allTimezones = \DateTimeZone::listIdentifiers();
        $limit = min($request->get('limit', 50), 100);

        $timezones = array_slice($allTimezones, 0, $limit);

        $tzData = [];
        foreach ($timezones as $tzName) {
            $tz = new \DateTimeZone($tzName);
            $sampleTime = new \DateTime('now', $tz);
            $tzData[] = [
                'name' => $tzName,
                'offset' => $tz->getOffset($sampleTime) / 3600,
                'abbr' => $sampleTime->format('T'),
            ];
        }

        $response = [
            'timezones' => $tzData,
            'total_available' => count($allTimezones),
            'returned' => count($tzData),
        ];

        return Response::json($response);
    }
}
```

## 3. Register Server

### File: `routes/ai.php`

```php
<?php

use App\Mcp\Servers\DateTimeServer;
use Laravel\Mcp\Facades\Mcp;

// Web Server - Accessible via HTTP POST
Mcp::web('/mcp/datetime', DateTimeServer::class);

// Local Server - Run as Artisan command
// Mcp::local('datetime', DateTimeServer::class);
```

## 4. Testing

### Using MCP Inspector

```bash
php artisan mcp:inspector /mcp/datetime
```

### Example Tool Calls

```json
{
    "tool": "get-current-datetime",
    "arguments": {}
}
```

```json
{
    "tool": "convert-timezone",
    "arguments": {
        "target_timezone": "America/New_York"
    }
}
```

```json
{
    "tool": "list-timezones",
    "arguments": {
        "limit": 10
    }
}
```

## 5. Expected Responses

### Get Current DateTime Response

```json
{
    "datetime": "2024-01-15 14:30:45 EST",
    "date": "2024-01-15",
    "time": "14:30:45",
    "timestamp": 1705331445,
    "timezone": {
        "name": "America/New_York",
        "offset": -18000,
        "abbr": "EST"
    },
    "iso_8601": "2024-01-15T14:30:45-05:00"
}
```

### Convert Timezone Response

```json
{
    "original": {
        "datetime": "2024-01-15 14:30:45 EST",
        "timezone": "America/New_York"
    },
    "converted": {
        "datetime": "2024-01-15 19:30:45 GMT",
        "timezone": "Europe/London",
        "utc_offset": 0
    },
    "iso_8601": "2024-01-15T19:30:45+00:00"
}
```

## 6. Features

✅ **Web Server** - Accessible via HTTP POST  
✅ **Timezone Conversion** - Full timezone conversion support  
✅ **Multiple Formats** - Flexible date/time formatting  
✅ **ISO 8601 Support** - Standard datetime formats  
✅ **Timezone Listing** - Browse available timezones  
✅ **Structured Responses** - Parseable JSON output

## 7. Client Integration Example

```javascript
// Example MCP client usage
const response = await mcpClient.callTool({
    name: 'convert-timezone',
    arguments: {
        target_timezone: 'Asia/Jakarta',
        format: 'F j, Y g:i:s A T',
    },
});

console.log(response.content[0].text);
```

## Troubleshooting

### Common Issues:

1. **Route not found** - Ensure `routes/ai.php` is loaded
2. **Tool not found** - Check class names and namespaces
3. **Response format** - Ensure tools return `Response::json()`

### Debug Steps:

1. Check server registration: `php artisan mcp:inspector /mcp/datetime`
2. Verify tool availability: Tools list should show 4 tools
3. Test individual tools with sample requests

## Next Steps

1. **Schema Validation** - Add input/output schemas for better validation
2. **Error Handling** - Enhanced error messages and validation
3. **Performance** - Add caching for timezone lists
4. **Extended Features** - Date calculations, business days, etc.
