<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class DateTimeServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'DateTime Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    public string $instructions = 'This server provides tools for getting current date, time, timezone information, and converting time between different timezones. Use these tools when you need to provide datetime context or perform timezone conversions.';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public array $tools = [];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    public array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    public array $prompts = [];
}
