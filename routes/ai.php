<?php

use App\Mcp\Servers\DateTimeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI/MCP Routes
|--------------------------------------------------------------------------
|
| Register your MCP servers here. You can register web servers (accessible
| via HTTP) or local servers (run as Artisan commands).
|
*/

// Web Server - Accessible via HTTP POST
Mcp::web('mcp/datetime', DateTimeServer::class);

// Local Server - Run as Artisan command
Mcp::local('datetime', DateTimeServer::class);
