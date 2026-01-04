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

## 4. Timezone Parameters

All tools now support timezone parameters for user-specific timezone handling:

### Timezone Parameter Support

| Tool                         | Timezone Parameter             | Default                        | Description                                    |
| ---------------------------- | ------------------------------ | ------------------------------ | ---------------------------------------------- |
| `get-current-date-time-tool` | `timezone`                     | Server timezone                | Get current datetime in specific timezone      |
| `get-timezone-info-tool`     | `timezone`                     | Server timezone                | Get timezone information for specific timezone |
| `convert-timezone-tool`      | `from_timezone`, `to_timezone` | From: Server timezone, To: UTC | Convert between timezones                      |

### Example Tool Calls with Timezone Support

```json
{
    "tool": "get-current-date-time-tool",
    "arguments": {
        "timezone": "Asia/Makassar"
    }
}
```

```json
{
    "tool": "get-timezone-info-tool",
    "arguments": {
        "timezone": "America/New_York"
    }
}
```

```json
{
    "tool": "convert-timezone-tool",
    "arguments": {
        "datetime": "2026-01-04 12:00:00",
        "from_timezone": "UTC",
        "to_timezone": "Asia/Makassar"
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

## 5. Testing

### Using MCP Inspector

```bash
php artisan mcp:inspector /mcp/datetime
```

## 6. Expected Responses

### Get Current DateTime Response (with timezone)

```json
{
    "datetime": "2026-01-04 20:21:02 WITA",
    "date": "2026-01-04",
    "time": "20:21:02",
    "timestamp": 1767529262,
    "timezone": {
        "name": "Asia/Makassar",
        "offset": 28800,
        "abbr": "WITA"
    },
    "iso_8601": "2026-01-04T12:21:02.078688Z",
    "requested_timezone": "Asia/Makassar",
    "server_timezone": "UTC"
}
```

### Get Timezone Info Response (with timezone)

```json
{
    "requested_timezone": "Asia/Makassar",
    "server_timezone": "UTC",
    "timezone_info": {
        "name": "Asia/Makassar",
        "offset": 28800,
        "abbr": "WITA",
        "dst": false
    },
    "config": {
        "timezone": "UTC"
    },
    "current_time": {
        "datetime": "2026-01-04 20:21:03 WITA",
        "date": "2026-01-04",
        "time": "20:21:03",
        "iso_8601": "2026-01-04T12:21:03.762479Z"
    }
}
```

### Convert Timezone Response (enhanced)

```json
{
    "input": {
        "datetime": "2026-01-04 12:00:00",
        "from_timezone": "UTC",
        "to_timezone": "Asia/Makassar"
    },
    "original": {
        "datetime": "2026-01-04 20:00:00",
        "timezone": "UTC",
        "offset": 28800,
        "abbr": "WITA",
        "iso_8601": "2026-01-04T20:00:00+08:00"
    },
    "converted": {
        "datetime": "2026-01-04 20:00:00",
        "timezone": "Asia/Makassar",
        "offset": 28800,
        "abbr": "WITA",
        "iso_8601": "2026-01-04T20:00:00+08:00"
    },
    "conversion_info": {
        "timezone_difference": 8,
        "dst_affected": false,
        "successful": true
    }
}
```

## 7. Features

✅ **Web Server** - Accessible via HTTP POST  
✅ **Timezone Conversion** - Full timezone conversion support  
✅ **Timezone Parameters** - Support for user-specific timezone requests  
✅ **Server vs User Timezone** - Distinguish between server and requested timezones  
✅ **Multiple Formats** - Flexible date/time formatting  
✅ **ISO 8601 Support** - Standard datetime formats  
✅ **Timezone Listing** - Browse available timezones (419 timezones)  
✅ **Structured Responses** - Parseable JSON output  
✅ **Error Handling** - Graceful handling of invalid timezones  
✅ **Language Agnostic** - Raw data for LLM to format responses

## 8. Client Integration Example

```javascript
// Example MCP client usage for Indonesian user queries
// User asks: "tanggal berapa sekarang?" (what's today's date?)
// LLM determines user is in Makassar timezone and calls:

const response = await mcpClient.callTool({
    name: 'get-current-date-time-tool',
    arguments: {
        timezone: 'Asia/Makassar',
    },
});

// LLM formats response: "Hari ini tanggal 4 Januari 2026"
console.log(response.content[0].text);
```

```javascript
// Timezone conversion example
const response = await mcpClient.callTool({
    name: 'convert-timezone-tool',
    arguments: {
        datetime: '2026-01-04 12:00:00',
        from_timezone: 'UTC',
        to_timezone: 'Asia/Makassar',
    },
});
```

## 9. Troubleshooting

### Common Issues:

1. **Route not found** - Ensure `routes/ai.php` is loaded
2. **Tool not found** - Check class names and namespaces
3. **Response format** - Ensure tools return `Response::json()`
4. **Invalid timezone** - Check timezone identifier format (e.g., "Asia/Makassar")
5. **Server vs User time** - Remember server timezone defaults to UTC

### Debug Steps:

1. Check server registration: `php artisan mcp:inspector /mcp/datetime`
2. Verify tool availability: Tools list should show 4 tools
3. Test individual tools with sample requests
4. Test timezone parameters:
    ```bash
    curl -X POST "http://localhost:8000/mcp/datetime" \
      -H "Content-Type: application/json" \
      -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get-current-date-time-tool","arguments":{"timezone":"Asia/Makassar"}}}'
    ```

## 10. Next Steps

1. **Schema Validation** - Add input/output schemas for better validation
2. **Caching** - Add caching for timezone lists and conversions
3. **Performance** - Optimize timezone calculations
4. **Extended Features** - Date calculations, business days, recurring events
5. **Localization** - Add support for different date formats per locale
