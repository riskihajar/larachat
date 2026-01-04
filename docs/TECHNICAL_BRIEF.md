# LaraChat - Technical Brief

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Prepared by:** Senior Fullstack Developer  
**Target Audience:** Tim Developer yang akan melanjutkan project

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Tech Stack Overview](#2-tech-stack-overview)
3. [Architecture Overview](#3-architecture-overview)
4. [Directory Structure](#4-directory-structure)
5. [Database Schema](#5-database-schema)
6. [Core Features & Implementation](#6-core-features--implementation)
7. [LLM Provider System](#7-llm-provider-system)
8. [MCP Tool Integration](#8-mcp-tool-integration)
9. [Authentication & Authorization](#9-authentication--authorization)
10. [Frontend Architecture](#10-frontend-architecture)
11. [Streaming Implementation](#11-streaming-implementation)
12. [API Reference](#12-api-reference)
13. [Testing Strategy](#13-testing-strategy)
14. [Development Workflow](#14-development-workflow)
15. [Environment Setup](#15-environment-setup)
16. [Common Tasks & How-To](#16-common-tasks--how-to)
17. [Code Conventions](#17-code-conventions)
18. [Git Workflow & Commit Conventions](#18-git-workflow--commit-conventions)
19. [Known Issues & Technical Debt](#19-known-issues--technical-debt)
20. [Future Roadmap](#20-future-roadmap)

---

## 1. Executive Summary

**LaraChat** adalah aplikasi AI chat modern yang menyediakan pengalaman seperti ChatGPT dengan dukungan multi-provider LLM. Aplikasi ini dibangun dengan Laravel 12 + Inertia.js v2 + React 19.

### Key Highlights

- **Multi-Provider LLM**: Support OpenAI (GPT-4o series) dan AWS Bedrock (Claude series)
- **Real-time Streaming**: Response streaming menggunakan Server-Sent Events (SSE)
- **MCP Integration**: Dynamic tool discovery melalui Model Context Protocol
- **LLM-Powered Tool Selection**: AI memilih tool yang tepat, bukan pattern matching manual
- **Role-Based Access Control**: Menggunakan Spatie Laravel Permission
- **Indonesian Language Support**: Response dalam Bahasa Indonesia untuk datetime queries

---

## 2. Tech Stack Overview

### Backend

| Component   | Technology                | Version |
| ----------- | ------------------------- | ------- |
| Framework   | Laravel                   | 12.44.0 |
| PHP         | PHP                       | 8.4.16  |
| Database    | SQLite                    | -       |
| MCP Server  | Laravel MCP               | 0.5.1   |
| Permissions | Spatie Laravel Permission | Latest  |

### Frontend

| Component        | Technology            | Version |
| ---------------- | --------------------- | ------- |
| Framework        | React                 | 19.0.0  |
| Server Bridge    | Inertia.js            | 2.0.4   |
| Styling          | Tailwind CSS          | 4.0.10  |
| UI Components    | Radix UI + Shadcn     | Latest  |
| Streaming        | @laravel/stream-react | 0.3.5   |
| Type-safe Routes | Laravel Wayfinder     | 0.1.12  |

### LLM Providers

| Provider    | Models                                                                    |
| ----------- | ------------------------------------------------------------------------- |
| OpenAI      | GPT-4o, GPT-4o Mini, GPT-4 Turbo, GPT-4, GPT-3.5 Turbo                    |
| AWS Bedrock | Claude Sonnet 4.5, Claude Sonnet 3.7, Claude Sonnet 3.5, Claude Haiku 3.5 |

---

## 3. Architecture Overview

### High-Level Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────────┐
│   Frontend  │────▶│  Controller │────▶│  LLM Provider   │
│   (React)   │     │  (Laravel)  │     │ (OpenAI/Bedrock)│
└─────────────┘     └──────┬──────┘     └────────┬────────┘
       ▲                   │                     │
       │                   ▼                     ▼
       │           ┌───────────────┐     ┌──────────────┐
       │           │ToolCoordinator│────▶│  MCP Server  │
       │           └───────────────┘     └──────────────┘
       │                   │
       └───────────────────┘
            SSE Streaming
```

### Request Flow Untuk Chat dengan Tools

```
1. User mengirim message
2. ChatController menerima request
3. LLMProviderFactory membuat provider instance (OpenAI/Bedrock)
4. Provider.withTools() mengaktifkan ToolCoordinator
5. ToolCoordinator.processMessage() menggunakan LLM untuk tool selection
6. Jika perlu tool: McpClient.callTool() mengeksekusi MCP tool
7. Result ditambahkan ke context, LLM membuat response natural
8. Response di-stream ke frontend via SSE
```

### Key Design Decisions

1. **Provider Injection Pattern**: ToolCoordinator menerima LLMProviderInterface, memastikan konsistensi provider
2. **Dynamic Tool Discovery**: Tools di-discover dari MCP server, bukan hardcoded
3. **LLM-Based Tool Selection**: LLM yang memilih tool, bukan regex/keyword matching
4. **Streaming-First**: Semua chat response di-stream untuk UX yang responsive

---

## 4. Directory Structure

```
larachat/
├── app/
│   ├── Concerns/
│   │   └── HasUlids.php              # Trait untuk ULID primary keys
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ChatController.php    # ⭐ Main chat logic
│   │   │   ├── Api/
│   │   │   │   └── ChatController.php # JSON API for chat list
│   │   │   ├── Auth/                 # Authentication controllers
│   │   │   └── Settings/             # User/Role management
│   │   │
│   │   ├── Middleware/
│   │   │   ├── HandleInertiaRequests.php  # Shared props
│   │   │   ├── HandleAppearance.php       # Theme handling
│   │   │   └── EnsureUserHasPermission.php
│   │   │
│   │   └── Requests/                 # Form request validation
│   │
│   ├── Mcp/
│   │   ├── Servers/
│   │   │   └── DateTimeServer.php    # ⭐ MCP server definition
│   │   └── Tools/
│   │       ├── GetCurrentDateTimeTool.php
│   │       ├── GetTimezoneInfoTool.php
│   │       ├── ConvertTimezoneTool.php
│   │       └── ListTimezonesTool.php
│   │
│   ├── Models/
│   │   ├── User.php                  # User dengan HasRoles
│   │   ├── Chat.php                  # Chat conversation
│   │   ├── Message.php               # Individual messages
│   │   ├── Role.php                  # Spatie Role
│   │   └── Permission.php            # Spatie Permission
│   │
│   ├── Policies/
│   │   └── ChatPolicy.php            # Chat authorization
│   │
│   ├── Services/
│   │   ├── LLM/
│   │   │   ├── Contracts/
│   │   │   │   └── LLMProviderInterface.php  # ⭐ Provider contract
│   │   │   ├── LLMProviderFactory.php        # ⭐ Factory pattern
│   │   │   └── Providers/
│   │   │       ├── OpenAIProvider.php        # ⭐ OpenAI implementation
│   │   │       └── BedrockProvider.php       # ⭐ Bedrock implementation
│   │   │
│   │   ├── McpClient.php             # ⭐ MCP HTTP client
│   │   └── ToolCoordinator.php       # ⭐ LLM tool selection
│   │
│   └── Providers/
│       └── AppServiceProvider.php
│
├── resources/js/
│   ├── app.tsx                       # Inertia entry point
│   ├── components/
│   │   ├── ui/                       # Shadcn components
│   │   ├── app-sidebar.tsx           # Main sidebar
│   │   ├── chat-list.tsx             # Chat history
│   │   ├── conversation.tsx          # Message display
│   │   ├── model-selector.tsx        # Provider/model dropdown
│   │   └── title-generator.tsx       # Auto title via SSE
│   │
│   ├── pages/
│   │   ├── chat.tsx                  # ⭐ Main chat page
│   │   ├── dashboard.tsx
│   │   ├── auth/                     # Login, register, etc
│   │   └── settings/                 # Profile, password, users, roles
│   │
│   ├── layouts/
│   │   ├── app-layout.tsx
│   │   ├── auth-layout.tsx
│   │   └── settings/layout.tsx
│   │
│   └── hooks/
│       ├── use-appearance.tsx
│       └── use-timezone.tsx
│
├── config/
│   └── llm.php                       # ⭐ LLM configuration
│
├── routes/
│   ├── web.php                       # Home route
│   ├── chat.php                      # Chat routes
│   ├── ai.php                        # MCP routes
│   ├── auth.php                      # Auth routes
│   └── settings.php                  # Settings routes
│
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│       └── RolePermissionSeeder.php  # Default roles/permissions
│
└── tests/
    ├── Feature/
    │   ├── ChatFlowTest.php
    │   ├── ChatStreamingTest.php
    │   ├── ToolProviderInjectionTest.php
    │   └── ...
    └── Unit/
```

### File Penting yang Harus Dipahami (Prioritas Tinggi)

1. `app/Http/Controllers/ChatController.php` - Main chat logic
2. `app/Services/LLM/Providers/OpenAIProvider.php` - OpenAI implementation
3. `app/Services/LLM/Providers/BedrockProvider.php` - Bedrock implementation
4. `app/Services/ToolCoordinator.php` - Tool selection logic
5. `app/Services/McpClient.php` - MCP communication
6. `resources/js/pages/chat.tsx` - Frontend chat page
7. `config/llm.php` - LLM configuration

---

## 5. Database Schema

### Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    users    │       │    chats    │       │  messages   │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id (ULID)   │──────▶│ id (ULID)   │──────▶│ id (ULID)   │
│ name        │   1:N │ user_id(FK) │   1:N │ chat_id(FK) │
│ email       │       │ title       │       │ type        │
│ password    │       │ provider    │       │ content     │
│ created_at  │       │ model       │       │ created_at  │
│ updated_at  │       │ created_at  │       │ updated_at  │
└─────────────┘       │ updated_at  │       └─────────────┘
                      └─────────────┘
```

### Tables Detail

#### `users`

```sql
id              VARCHAR (ULID, PK)
name            VARCHAR
email           VARCHAR (UNIQUE)
email_verified_at DATETIME
password        VARCHAR
remember_token  VARCHAR
created_at      DATETIME
updated_at      DATETIME
```

#### `chats`

```sql
id          VARCHAR (ULID, PK)
user_id     VARCHAR (FK -> users.id, CASCADE DELETE)
title       VARCHAR (default: "Untitled")
provider    VARCHAR (e.g., "openai", "bedrock")
model       VARCHAR (e.g., "gpt-4o", "us.anthropic.claude-sonnet-4...")
created_at  DATETIME
updated_at  DATETIME
```

#### `messages`

```sql
id          VARCHAR (ULID, PK)
chat_id     VARCHAR (FK -> chats.id, CASCADE DELETE)
type        VARCHAR ("prompt" | "response" | "error")
content     TEXT
created_at  DATETIME
updated_at  DATETIME
```

### Permission Tables (Spatie)

```
roles                   - id, name, guard_name
permissions             - id, name, guard_name
role_has_permissions    - role_id, permission_id
model_has_roles         - role_id, model_type, model_id
model_has_permissions   - permission_id, model_type, model_id
```

---

## 6. Core Features & Implementation

### 6.1 Chat Creation Flow

```php
// ChatController.php

// Ketika user pertama kali masuk atau klik "+"
public function index()
{
    if (Auth::check()) {
        // Cari chat kosong yang sudah ada, atau buat baru
        $chat = $this->findOrCreateEmptyChat();
        return redirect()->route('chat.show', $chat);
    }

    // Guest mode - tidak menyimpan chat
    return Inertia::render('chat', ['chat' => null]);
}

private function findOrCreateEmptyChat(): Chat
{
    // Cari chat dengan title "Untitled" dan tanpa message
    $emptyChat = Auth::user()->chats()
        ->where('title', 'Untitled')
        ->whereDoesntHave('messages')
        ->latest()
        ->first();

    if ($emptyChat) {
        return $emptyChat;  // Reuse existing empty chat
    }

    // Buat chat baru
    return Auth::user()->chats()->create([
        'title' => 'Untitled',
        'provider' => config('llm.default'),
        'model' => config("llm.default_models.{$provider}"),
    ]);
}
```

### 6.2 Message Streaming Flow

```php
// ChatController@stream

public function stream(Request $request, ?Chat $chat = null)
{
    // 1. Determine provider & model
    $providerName = $request->input('provider') ?? $chat?->provider ?? config('llm.default');
    $modelName = $request->input('model') ?? $chat?->model;

    // 2. Create provider instance
    $provider = LLMProviderFactory::make($providerName, $modelName);

    // 3. Enable tools
    if ($provider instanceof OpenAIProvider || $provider instanceof BedrockProvider) {
        $provider = $provider->withTools();
    }

    // 4. Stream response
    return response()->stream(function () use ($request, $chat, $provider) {
        $messages = $request->input('messages', []);

        // Save user messages to DB
        if ($chat) {
            foreach ($messages as $message) {
                if (!isset($message['id'])) {
                    $chat->messages()->create([...]);
                }
            }
        }

        // Stream from LLM
        $fullResponse = '';
        foreach ($provider->streamWithTools($messages, $userContext) as $chunk) {
            $fullResponse .= $chunk;
            echo $chunk;
            @ob_flush();
            @flush();
        }

        // Save AI response
        if ($chat && $fullResponse) {
            $chat->messages()->create([
                'type' => 'response',
                'content' => $fullResponse,
            ]);
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

### 6.3 Auto Title Generation

```php
// Setelah first response, generate title otomatis
if ($chat->title === 'Untitled') {
    $provider = LLMProviderFactory::make($chat->provider);
    $generatedTitle = $provider->generateTitle($firstMessage->content);
    $chat->update(['title' => $generatedTitle]);
}
```

---

## 7. LLM Provider System

### 7.1 Provider Interface

```php
// app/Services/LLM/Contracts/LLMProviderInterface.php

interface LLMProviderInterface
{
    public function stream(array $messages): \Generator;
    public function generateTitle(string $firstMessage): string;
    public function getName(): string;
    public function getModel(): string;
}
```

### 7.2 Factory Pattern

```php
// app/Services/LLM/LLMProviderFactory.php

class LLMProviderFactory
{
    public static function make(?string $provider = null, ?string $model = null): LLMProviderInterface
    {
        $provider = $provider ?? config('llm.default');
        $model = $model ?? config("llm.default_models.{$provider}");

        return match ($provider) {
            'openai' => new OpenAIProvider($model),
            'bedrock' => new BedrockProvider($model),
            default => throw new InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
```

### 7.3 OpenAI Provider

```php
// app/Services/LLM/Providers/OpenAIProvider.php

class OpenAIProvider implements LLMProviderInterface
{
    protected ?ToolCoordinator $toolCoordinator = null;

    public function withTools(): self
    {
        $this->toolCoordinator = new ToolCoordinator($this);
        return $this;
    }

    public function streamWithTools(array $messages, array $userContext = []): \Generator
    {
        // Check if tools enabled
        if (!$this->toolCoordinator) {
            yield from $this->stream($messages);
            return;
        }

        // Get latest user message
        $userMessage = $messages[array_key_last($messages)]['content'] ?? '';
        $userTimezone = $userContext['timezone'] ?? 'Asia/Makassar';

        // Check if message needs tool
        $toolRequest = $this->toolCoordinator->processMessage($userMessage, $userTimezone);

        if (!$toolRequest['needs_tool']) {
            yield from $this->stream($messages);
            return;
        }

        // Execute tool first
        $toolResult = $this->toolCoordinator->executeTool(...);

        // Add tool result to context
        $messages[] = [
            'role' => 'system',
            'content' => "Tool executed with data: " . json_encode($toolResult) .
                        ". Provide natural Indonesian response."
        ];

        // Stream final response
        yield from $this->streamWithOpenAI($messages);
    }
}
```

### 7.4 Bedrock Provider (Custom Streaming)

AWS Bedrock menggunakan binary event stream format yang berbeda. Implementation custom:

```php
// app/Services/LLM/Providers/BedrockProvider.php

protected function streamWithGuzzle(array $payload): \Generator
{
    $region = config('llm.bedrock.region');
    $url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$this->model}/invoke-with-response-stream";

    // Sign request dengan AWS Signature V4
    $credentials = new Credentials(config('llm.bedrock.key'), config('llm.bedrock.secret'));
    $signer = new SignatureV4('bedrock', $region);
    $signedRequest = $signer->signRequest($request, $credentials);

    // Parse binary event stream
    while (!$stream->eof()) {
        // Read prelude (8 bytes)
        $prelude = $stream->read(8);
        $totalLength = unpack('N', substr($prelude, 0, 4))[1];
        $headersLength = unpack('N', substr($prelude, 4, 4))[1];

        // Read and parse payload
        // ... parsing logic ...

        if ($eventType === 'chunk') {
            $decodedPayload = base64_decode($chunk['bytes']);
            $data = json_decode($decodedPayload, true);

            if ($data['type'] === 'content_block_delta') {
                yield $data['delta']['text'];
            }
        }
    }
}
```

### 7.5 Configuration

```php
// config/llm.php

return [
    'default' => env('LLM_DEFAULT_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'title_model' => env('OPENAI_TITLE_MODEL', 'gpt-4o-mini'),
    ],

    'bedrock' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'model' => env('AWS_BEDROCK_DEFAULT_MODEL', 'us.anthropic.claude-sonnet-4-20250514-v1:0'),
    ],

    'providers' => [
        'openai' => 'OpenAI',
        'bedrock' => 'AWS Bedrock',
    ],

    'models' => [
        'openai' => [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            // ...
        ],
        'bedrock' => [
            'us.anthropic.claude-sonnet-4-20250514-v1:0' => 'Claude Sonnet 4.5',
            // ...
        ],
    ],
];
```

---

## 8. MCP Tool Integration

### 8.1 Overview

MCP (Model Context Protocol) memungkinkan LLM untuk menggunakan external tools. LaraChat mengimplementasikan MCP server untuk datetime operations.

### 8.2 Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│ ToolCoordinator │─────▶│    McpClient    │─────▶│  DateTimeServer │
│                 │      │                 │      │    (MCP)        │
│ - processMessage│      │ - getAvailable  │      │                 │
│ - executeTool   │      │   Tools()       │      │ - tools/list    │
│ - llmSelectTool │      │ - callTool()    │      │ - tools/call    │
└─────────────────┘      └─────────────────┘      └─────────────────┘
```

### 8.3 MCP Server Definition

```php
// app/Mcp/Servers/DateTimeServer.php

class DateTimeServer extends Server
{
    protected string $name = 'DateTime Server';
    protected string $version = '1.0.0';

    public string $instructions = 'This server provides tools for getting current date,
        time, timezone information, and converting time between different timezones.';

    public array $tools = [
        GetCurrentDateTimeTool::class,
        GetTimezoneInfoTool::class,
        ConvertTimezoneTool::class,
        ListTimezonesTool::class,
    ];
}
```

### 8.4 MCP Tool Example

```php
// app/Mcp/Tools/GetCurrentDateTimeTool.php

#[IsReadOnly]
class GetCurrentDateTimeTool extends Tool
{
    protected string $description = 'Gets complete current datetime with timezone information.';

    public function handle(Request $request): Response
    {
        $timezone = $request->get('timezone') ?? 'Asia/Makassar';
        $now = now()->timezone($timezone);

        return Response::json([
            'datetime' => $now->format('Y-m-d H:i:s T'),
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s'),
            'timestamp' => $now->timestamp,
            'timezone' => [
                'name' => $timezone,
                'offset' => $now->timezone->getOffset($now),
                'abbr' => $now->format('T'),
            ],
            'iso_8601' => $now->toISOString(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('Optional timezone identifier (e.g., "Asia/Makassar", "UTC")'),
        ];
    }
}
```

### 8.5 McpClient Implementation

```php
// app/Services/McpClient.php

class McpClient
{
    private string $baseUrl;
    private ?array $cachedTools = null;

    public function __construct()
    {
        $this->baseUrl = config('app.url') . '/mcp/datetime';
    }

    // Dynamic tool discovery
    public function getAvailableTools(): array
    {
        if ($this->cachedTools !== null) {
            return $this->cachedTools;
        }

        $response = Http::post($this->baseUrl, [
            'jsonrpc' => '2.0',
            'id' => now()->timestamp,
            'method' => 'tools/list',
            'params' => []
        ]);

        $this->cachedTools = $response->json()['result']['tools'] ?? [];
        return $this->cachedTools;
    }

    // Execute tool via JSON-RPC
    public function callTool(string $toolName, array $arguments = []): array
    {
        $response = Http::post($this->baseUrl, [
            'jsonrpc' => '2.0',
            'id' => now()->timestamp,
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments
            ]
        ]);

        $textContent = $response->json()['result']['content'][0]['text'];
        return json_decode($textContent, true);
    }
}
```

### 8.6 ToolCoordinator (LLM-Based Selection)

```php
// app/Services/ToolCoordinator.php

class ToolCoordinator
{
    private McpClient $mcpClient;
    private LLMProviderInterface $llmProvider;

    public function __construct(LLMProviderInterface $llmProvider)
    {
        $this->llmProvider = $llmProvider;
        $this->mcpClient = new McpClient();
    }

    // LLM memilih tool yang tepat
    private function llmSelectTool(string $message, string $userTimezone): array
    {
        $availableTools = $this->mcpClient->getAvailableTools();

        // Prepare tool descriptions for LLM
        $toolDescriptions = [];
        foreach ($availableTools as $tool) {
            $toolDescriptions[] = "- {$tool['name']}: {$tool['description']}";
        }

        $prompt = "Based ONLY on the user message and available tools below,
                   select the most appropriate tool.

                   Available tools:
                   " . implode("\n", $toolDescriptions) . "

                   User message: \"{$message}\"
                   User timezone: {$userTimezone}

                   Respond with ONLY the tool name.";

        // Use injected provider for consistency
        $response = $this->callLLMForToolSelection($prompt);
        return ['tool_name' => trim($response), 'reason' => 'LLM selection'];
    }
}
```

### 8.7 Menambah MCP Tool Baru

1. **Buat Tool Class**:

```php
// app/Mcp/Tools/MyNewTool.php

class MyNewTool extends Tool
{
    protected string $description = 'Description for LLM';

    public function handle(Request $request): Response
    {
        // Tool logic
        return Response::json($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'param1' => $schema->string()->description('Param description'),
        ];
    }
}
```

2. **Register di Server**:

```php
// app/Mcp/Servers/DateTimeServer.php

public array $tools = [
    // existing tools...
    MyNewTool::class,
];
```

3. **Done!** Tool akan otomatis di-discover oleh ToolCoordinator.

---

## 9. Authentication & Authorization

### 9.1 Roles & Permissions

```php
// Permissions yang tersedia
$permissions = [
    // Chat
    'chat.view.own',    'chat.view.all',
    'chat.create',
    'chat.update.own',  'chat.update.all',
    'chat.delete.own',  'chat.delete.all',

    // User management
    'user.view', 'user.create', 'user.update', 'user.delete',

    // Role management
    'role.view', 'role.create', 'role.update', 'role.delete',

    // Admin
    'admin.access',
];

// Default Roles
// admin: All permissions
// user: chat.view.own, chat.create, chat.update.own, chat.delete.own
```

### 9.2 ChatPolicy

```php
// app/Policies/ChatPolicy.php

public function view(User $user, Chat $chat): bool
{
    if ($user->can('chat.view.all')) {
        return true;  // Admin
    }
    return $user->can('chat.view.own') && $user->id === $chat->user_id;
}

public function update(User $user, Chat $chat): bool
{
    if ($user->can('chat.update.all')) {
        return true;  // Admin
    }
    return $user->can('chat.update.own') && $user->id === $chat->user_id;
}
```

### 9.3 Default Admin User

```
Email: admin@larachat.test
Password: password
```

Run seeder: `php artisan db:seed --class=RolePermissionSeeder`

---

## 10. Frontend Architecture

### 10.1 Main Chat Component

```tsx
// resources/js/pages/chat.tsx

function ChatWithStream({ chat, auth, flash, availableModels }) {
    const [messages, setMessages] = useState<Message[]>(chat?.messages || []);
    const [selectedModel, setSelectedModel] = useState<string>(getInitialModel());

    // Stream hook dari @laravel/stream-react
    const { data, send, isStreaming, cancel } = useStream(chat?.id ? `/chat/${chat.id}/stream` : '/chat/stream');

    const handleSubmit = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            const query = inputValue.trim();

            // Add previous response if exists
            if (data && data.trim()) {
                toAdd.push({ type: 'response', content: data });
            }
            toAdd.push({ type: 'prompt', content: query });

            setMessages((prev) => [...prev, ...toAdd]);
            send({ messages: [...messages, ...toAdd], model: selectedModel });
            setInputValue('');
        },
        [send, data, messages, selectedModel, inputValue],
    );

    return (
        <AppLayout>
            <Conversation messages={messages} streamingData={data} isStreaming={isStreaming} />
            <InputForm onSubmit={handleSubmit} />
        </AppLayout>
    );
}
```

### 10.2 Key Components

| Component        | File                             | Description                |
| ---------------- | -------------------------------- | -------------------------- |
| `AppLayout`      | `layouts/app-layout.tsx`         | Main layout dengan sidebar |
| `Conversation`   | `components/conversation.tsx`    | Display messages           |
| `AppSidebar`     | `components/app-sidebar.tsx`     | Navigation + chat list     |
| `ChatList`       | `components/chat-list.tsx`       | Chat history               |
| `ModelSelector`  | `components/model-selector.tsx`  | Provider/model dropdown    |
| `TitleGenerator` | `components/title-generator.tsx` | Auto title via SSE         |

### 10.3 Inertia Shared Props

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user(),
            'permissions' => $request->user()?->getAllPermissions()->pluck('name')->toArray(),
        ],
        'sidebarOpen' => session('sidebarOpen', true),
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
            'info' => session('info'),
        ],
        'ziggy' => fn () => [
            ...(new Ziggy)->toArray(),
            'location' => $request->url(),
        ],
    ];
}
```

---

## 11. Streaming Implementation

### 11.1 Backend (PHP)

```php
// Critical headers untuk streaming
return response()->stream($callback, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',  // Disable nginx buffering
]);

// Di dalam callback
while (ob_get_level()) {
    ob_end_clean();  // Disable PHP output buffering
}

foreach ($provider->stream($messages) as $chunk) {
    echo $chunk;
    @ob_flush();
    @flush();
}
```

### 11.2 Frontend (React)

```tsx
import { useStream } from '@laravel/stream-react';

const { data, send, isStreaming, isFetching, cancel, id } = useStream('/chat/stream', {
    onData: (chunk) => console.log('Received:', chunk),
    onResponse: (response) => console.log('Started:', response.status),
    onFinish: () => console.log('Finished'),
});

// Send request
send({ messages, model: selectedModel });

// Cancel streaming
cancel();
```

### 11.3 CSRF Exemption

```php
// bootstrap/app.php

$middleware->validateCsrfTokens(except: [
    'chat/stream',
    'chat/*/stream',
]);
```

---

## 12. API Reference

### Routes Summary

| Method | Route                       | Name                | Description              |
| ------ | --------------------------- | ------------------- | ------------------------ |
| GET    | `/`                         | `home`              | Home page                |
| GET    | `/api/chats`                | `api.chats.index`   | List user's chats (JSON) |
| POST   | `/chat`                     | `chat.store`        | Create new chat          |
| GET    | `/chat/{chat}`              | `chat.show`         | Show chat page           |
| PATCH  | `/chat/{chat}`              | `chat.update`       | Update chat title        |
| DELETE | `/chat/{chat}`              | `chat.destroy`      | Delete chat              |
| POST   | `/chat/stream`              | `chat.stream`       | Guest streaming          |
| POST   | `/chat/{chat}/stream`       | `chat.show.stream`  | Auth streaming           |
| GET    | `/chat/{chat}/title-stream` | `chat.title.stream` | Title generation SSE     |
| POST   | `/mcp/datetime`             | -                   | MCP server endpoint      |

### Request/Response Examples

#### Create Chat

```http
POST /chat
Content-Type: application/json

{
    "title": "My Chat",           // optional
    "firstMessage": "Hello",      // optional, triggers auto-stream
    "model": "openai:gpt-4o"      // optional, format: "provider:model"
}

Response: Redirect to /chat/{id}
```

#### Stream Messages

```http
POST /chat/{id}/stream
Content-Type: application/json

{
    "messages": [
        {"type": "prompt", "content": "What time is it?"}
    ],
    "model": "openai:gpt-4o",
    "timezone": "Asia/Makassar"
}

Response: text/event-stream
```

#### List Chats

```http
GET /api/chats

Response:
[
    {
        "id": "01JFGK...",
        "title": "My Chat",
        "provider": "openai",
        "model": "gpt-4o",
        "created_at": "2026-01-05T10:00:00Z",
        "updated_at": "2026-01-05T10:30:00Z"
    }
]
```

---

## 13. Testing Strategy

### 13.1 Test Structure

```
tests/
├── Feature/
│   ├── ChatFlowTest.php            # E2E chat creation flow
│   ├── ChatStreamingTest.php       # Streaming functionality
│   ├── ChatEmptyReuseTest.php      # Empty chat reuse logic
│   ├── ToolProviderInjectionTest.php # Provider injection
│   └── Auth/                       # Authentication tests
└── Unit/
    └── ChatPersistenceTest.php     # Database persistence
```

### 13.2 Running Tests

```bash
# All tests
php artisan test

# Specific file
php artisan test tests/Feature/ChatFlowTest.php

# Filter by name
php artisan test --filter=test_plus_button_creates_blank_chat

# With coverage
php artisan test --coverage
```

### 13.3 Test Examples

```php
// Feature test
it('creates chat with first message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/chat', ['firstMessage' => 'Hello']);

    $response->assertRedirect();
    expect($user->chats)->toHaveCount(1);
    expect($user->chats->first()->messages)->toHaveCount(1);
});

// Unit test for provider injection
it('injects correct provider to ToolCoordinator', function () {
    $provider = LLMProviderFactory::make('openai');
    $coordinator = new ToolCoordinator($provider);

    $reflection = new ReflectionClass($coordinator);
    $prop = $reflection->getProperty('llmProvider');
    $prop->setAccessible(true);

    expect($prop->getValue($coordinator)->getName())->toBe('openai');
});
```

---

## 14. Development Workflow

### 14.1 Essential Commands

```bash
# Start development
composer run dev              # Runs server, queue, logs, vite

# Individual processes
php artisan serve             # Laravel server
npm run dev                   # Vite dev server
php artisan queue:work        # Queue worker

# Code quality
vendor/bin/pint --dirty       # PHP formatting (Laravel Pint)
npm run lint                  # ESLint
npm run format                # Prettier

# Build
npm run build                 # Production build

# Database
php artisan migrate           # Run migrations
php artisan db:seed           # Run all seeders
php artisan migrate:fresh --seed  # Reset DB with seed

# Testing
php artisan test              # Run all tests
php artisan test --filter=X   # Run specific test
```

### 14.2 Wayfinder (Type-safe Routes)

```bash
# Generate TypeScript route functions
php artisan wayfinder:generate
```

Usage in frontend:

```tsx
import { show, store } from '@/actions/App/Http/Controllers/ChatController';

// Get URL
show.url(chatId); // "/chat/123"

// Get route object
show(chatId); // { url: "/chat/123", method: "get" }

// Use with Inertia form
form.submit(store());
```

---

## 15. Environment Setup

### 15.1 Requirements

- PHP 8.4+
- Node.js 20+
- Composer 2+
- SQLite (or MySQL/PostgreSQL)

### 15.2 Installation

```bash
# Clone & install
git clone <repo>
cd larachat
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
touch database/database.sqlite
php artisan migrate --seed

# Build assets
npm run build

# Start development
composer run dev
```

### 15.3 Environment Variables

```env
# App
APP_NAME=LaraChat
APP_URL=http://larachat.test

# Database
DB_CONNECTION=sqlite

# LLM
LLM_DEFAULT_PROVIDER=openai

# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o
OPENAI_TITLE_MODEL=gpt-4o-mini

# AWS Bedrock (alternative)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BEDROCK_DEFAULT_MODEL=us.anthropic.claude-sonnet-4-20250514-v1:0
```

---

## 16. Common Tasks & How-To

### 16.1 Menambah LLM Provider Baru

1. Buat provider class:

```php
// app/Services/LLM/Providers/NewProvider.php

class NewProvider implements LLMProviderInterface
{
    public function stream(array $messages): \Generator { ... }
    public function generateTitle(string $firstMessage): string { ... }
    public function getName(): string { return 'newprovider'; }
    public function getModel(): string { return $this->model; }
}
```

2. Register di factory:

```php
// LLMProviderFactory.php

return match ($provider) {
    'openai' => new OpenAIProvider($model),
    'bedrock' => new BedrockProvider($model),
    'newprovider' => new NewProvider($model),  // Add
    default => throw new InvalidArgumentException(...),
};
```

3. Update config:

```php
// config/llm.php

'providers' => [
    // existing...
    'newprovider' => 'New Provider Display Name',
],

'models' => [
    // existing...
    'newprovider' => [
        'model-id-1' => 'Model Display Name',
    ],
],
```

### 16.2 Menambah MCP Tool

```php
// 1. Buat tool class
// app/Mcp/Tools/CalculatorTool.php

class CalculatorTool extends Tool
{
    protected string $description = 'Performs mathematical calculations';

    public function handle(Request $request): Response
    {
        $expression = $request->get('expression');
        $result = eval("return {$expression};");
        return Response::json(['result' => $result]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expression' => $schema->string()
                ->description('Math expression to evaluate'),
        ];
    }
}

// 2. Register di server
// app/Mcp/Servers/DateTimeServer.php (atau buat server baru)

public array $tools = [
    // existing...
    CalculatorTool::class,
];
```

### 16.3 Menambah Permission Baru

```php
// 1. Tambah ke seeder
// database/seeders/RolePermissionSeeder.php

$permissions = [
    // existing...
    'new.permission',
];

// 2. Run seeder
php artisan db:seed --class=RolePermissionSeeder

// 3. Gunakan di code
if ($user->can('new.permission')) { ... }
```

### 16.4 Menambah Halaman Settings Baru

1. Buat controller:

```bash
php artisan make:controller Settings/MySettingsController
```

2. Buat page:

```tsx
// resources/js/pages/settings/my-settings.tsx

export default function MySettings() {
    return (
        <SettingsLayout>
            <Head title="My Settings" />
            {/* content */}
        </SettingsLayout>
    );
}
```

3. Tambah route:

```php
// routes/settings.php

Route::get('/settings/my-settings', [MySettingsController::class, 'index'])
    ->name('settings.my-settings');
```

4. Update sidebar navigation.

---

## 17. Code Conventions

### 17.1 PHP Conventions

#### Naming Conventions

| Type            | Convention                | Example                                        |
| --------------- | ------------------------- | ---------------------------------------------- |
| Class           | PascalCase                | `ChatController`, `OpenAIProvider`             |
| Method          | camelCase                 | `findOrCreateEmptyChat()`, `streamWithTools()` |
| Variable        | camelCase                 | `$userTimezone`, `$toolCoordinator`            |
| Constant        | SCREAMING_SNAKE_CASE      | `MAX_TOKENS`, `DEFAULT_MODEL`                  |
| Property        | camelCase                 | `protected string $model`                      |
| Config key      | snake_case                | `llm.default_provider`                         |
| Route name      | dot.notation              | `chat.show`, `settings.users.index`            |
| Database table  | snake_case (plural)       | `users`, `chat_messages`                       |
| Database column | snake_case                | `user_id`, `created_at`                        |
| Migration       | snake_case with timestamp | `2025_05_28_173049_create_chats_table`         |

#### Code Style

```php
<?php

declare(strict_types=1);  // Selalu gunakan strict types

namespace App\Services;

use App\Models\User;  // Imports diurutkan alphabetically

class MyService
{
    // Constructor property promotion (PHP 8+)
    public function __construct(
        private readonly UserRepository $users,
        private readonly string $defaultTimezone = 'Asia/Makassar',
    ) {}

    // Explicit return types WAJIB
    public function findUser(int $id): ?User
    {
        return $this->users->find($id);
    }

    // Gunakan curly braces untuk semua control structures
    public function isValid(string $input): bool
    {
        if (empty($input)) {
            return false;
        }

        return true;
    }

    // Array syntax modern
    public function getData(): array
    {
        return [
            'key' => 'value',
            'nested' => [
                'item' => 1,
            ],
        ];
    }
}
```

#### PHPDoc Standards

```php
/**
 * Process user message and determine tool requirements.
 *
 * @param string $message User's input message
 * @param string $timezone User's timezone (default: Asia/Makassar)
 * @return array{needs_tool: bool, tool_name?: string, arguments?: array}
 *
 * @throws \InvalidArgumentException When message is empty
 */
public function processMessage(string $message, string $timezone = 'Asia/Makassar'): array
{
    // ...
}
```

#### Eloquent & Database

```php
// ✅ GOOD: Use Eloquent relationships
$user->chats()->where('title', 'Untitled')->first();

// ❌ BAD: Raw DB queries
DB::table('chats')->where('user_id', $user->id)->first();

// ✅ GOOD: Eager loading
$chats = Chat::with('messages')->get();

// ❌ BAD: N+1 query
$chats = Chat::all();
foreach ($chats as $chat) {
    echo $chat->messages->count();  // N+1!
}

// ✅ GOOD: Query scopes
public function scopeEmpty(Builder $query): Builder
{
    return $query->where('title', 'Untitled')
                 ->whereDoesntHave('messages');
}
```

### 17.2 TypeScript/React Conventions

#### Naming Conventions

| Type             | Convention                  | Example                               |
| ---------------- | --------------------------- | ------------------------------------- |
| Component        | PascalCase                  | `ChatList`, `ModelSelector`           |
| Hook             | camelCase with `use` prefix | `useAppearance`, `useTimezone`        |
| Function         | camelCase                   | `handleSubmit`, `formatMessage`       |
| Variable         | camelCase                   | `isStreaming`, `selectedModel`        |
| Constant         | SCREAMING_SNAKE_CASE        | `MAX_MESSAGE_LENGTH`                  |
| Type/Interface   | PascalCase                  | `Message`, `ChatType`                 |
| File (component) | kebab-case                  | `chat-list.tsx`, `model-selector.tsx` |
| File (page)      | kebab-case                  | `chat.tsx`, `dashboard.tsx`           |

#### Component Structure

```tsx
// 1. Imports (grouped: react, external, internal, types)
import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Message, ChatType } from '@/types';

// 2. Types (jika tidak di file terpisah)
type Props = {
    chat: ChatType;
    onUpdate: (title: string) => void;
};

// 3. Component
export default function ChatHeader({ chat, onUpdate }: Props) {
    // 3a. Hooks first
    const [isEditing, setIsEditing] = useState(false);
    const { auth } = usePage().props;

    // 3b. Derived state
    const canEdit = auth.user?.id === chat.user_id;

    // 3c. Effects
    useEffect(() => {
        // ...
    }, []);

    // 3d. Handlers
    const handleSave = useCallback(() => {
        onUpdate(newTitle);
        setIsEditing(false);
    }, [onUpdate]);

    // 3e. Render
    return (
        <header className="flex items-center gap-4">
            <h1>{chat.title}</h1>
            {canEdit && <Button onClick={() => setIsEditing(true)}>Edit</Button>}
        </header>
    );
}
```

#### Tailwind CSS Conventions

```tsx
// ✅ GOOD: Gunakan utility classes
<div className="flex items-center gap-4 p-4 bg-background rounded-lg">

// ✅ GOOD: Conditional classes dengan clsx/cn
<button className={cn(
    "px-4 py-2 rounded-md",
    isActive && "bg-primary text-primary-foreground",
    isDisabled && "opacity-50 cursor-not-allowed"
)}>

// ✅ GOOD: Group dengan gap, bukan margins
<div className="flex gap-4">
    <Item />
    <Item />
</div>

// ❌ BAD: Individual margins
<div className="flex">
    <Item className="mr-4" />
    <Item />
</div>

// ✅ GOOD: Dark mode support
<div className="bg-white dark:bg-gray-900">
```

### 17.3 File Organization

```
// Controller methods order
public function index() {}      // List
public function create() {}     // Show create form
public function store() {}      // Handle create
public function show() {}       // Show single
public function edit() {}       // Show edit form
public function update() {}     // Handle update
public function destroy() {}    // Handle delete

// React component file structure
components/
├── ui/                  # Shadcn/primitive components
│   ├── button.tsx
│   └── input.tsx
├── chat-list.tsx        # Feature components (kebab-case)
├── model-selector.tsx
└── conversation.tsx

// Hooks
hooks/
├── use-appearance.tsx   # Always use- prefix
└── use-timezone.tsx
```

---

## 18. Git Workflow & Commit Conventions

### 18.1 Commit Message Format

Menggunakan **Conventional Commits** specification:

```
<type>(<scope>): <subject>

[optional body]

[optional footer(s)]
```

### 18.2 Commit Types

| Type       | Description                         | Example                                    |
| ---------- | ----------------------------------- | ------------------------------------------ |
| `feat`     | Fitur baru                          | `feat: add model selector dropdown`        |
| `fix`      | Bug fix                             | `fix: resolve streaming timeout issue`     |
| `docs`     | Dokumentasi only                    | `docs: update API documentation`           |
| `style`    | Formatting, tidak mengubah logic    | `style: format code with Pint`             |
| `refactor` | Refactoring tanpa mengubah behavior | `refactor: extract LLM provider interface` |
| `perf`     | Performance improvement             | `perf: optimize database queries`          |
| `test`     | Menambah atau memperbaiki tests     | `test: add chat streaming tests`           |
| `build`    | Build system atau dependencies      | `build: upgrade to Laravel 12`             |
| `ci`       | CI/CD configuration                 | `ci: add GitHub Actions workflow`          |
| `chore`    | Maintenance tasks                   | `chore: update .gitignore`                 |
| `revert`   | Revert commit sebelumnya            | `revert: revert "feat: add feature X"`     |

### 18.3 Scope (Optional)

Scope menunjukkan area yang diubah:

| Scope    | Area                |
| -------- | ------------------- |
| `chat`   | Chat feature        |
| `auth`   | Authentication      |
| `llm`    | LLM providers       |
| `mcp`    | MCP tools           |
| `ui`     | Frontend components |
| `api`    | API endpoints       |
| `db`     | Database/migrations |
| `config` | Configuration       |

### 18.4 Commit Message Examples

#### Feature

```bash
# Simple feature
git commit -m "feat: add dark mode toggle"

# Feature with scope
git commit -m "feat(chat): add message edit functionality"

# Feature with body
git commit -m "feat(llm): add Gemini provider support

- Add GeminiProvider class
- Update LLMProviderFactory
- Add configuration options"
```

#### Bug Fix

```bash
# Simple fix
git commit -m "fix: prevent empty chat creation"

# Fix with scope
git commit -m "fix(streaming): resolve buffer timeout on nginx"

# Fix with issue reference
git commit -m "fix(auth): resolve session persistence issue

Fixes #123"
```

#### Refactoring

```bash
git commit -m "refactor(llm): extract common streaming logic"

git commit -m "refactor: reorganize service layer structure

- Move providers to dedicated namespace
- Extract interfaces
- Update DI bindings"
```

#### Documentation

```bash
git commit -m "docs: add API endpoint documentation"
git commit -m "docs(readme): update installation instructions"
```

#### Chore & Maintenance

```bash
git commit -m "chore: update dependencies"
git commit -m "chore(deps): bump laravel/framework to 12.45"
git commit -m "chore: remove unused DateTimeFormatter class"
```

#### Tests

```bash
git commit -m "test: add unit tests for ToolCoordinator"
git commit -m "test(chat): add streaming integration tests"
```

#### Breaking Changes

```bash
git commit -m "feat(api)!: change chat endpoint response format

BREAKING CHANGE: The /api/chats endpoint now returns
paginated results instead of a flat array."
```

### 18.5 Branch Naming

```bash
# Feature branch
feature/add-model-selector
feature/chat-export

# Bug fix branch
fix/streaming-timeout
fix/auth-session-issue

# Hotfix (production urgent)
hotfix/security-patch

# Release branch
release/1.2.0

# Chore/maintenance
chore/update-dependencies
chore/cleanup-unused-code
```

### 18.6 Git Workflow

```bash
# 1. Buat branch dari main/develop
git checkout main
git pull origin main
git checkout -b feature/my-feature

# 2. Commit changes (small, atomic commits)
git add app/Services/NewService.php
git commit -m "feat(service): add new service class"

git add tests/Feature/NewServiceTest.php
git commit -m "test: add tests for new service"

# 3. Push branch
git push -u origin feature/my-feature

# 4. Create Pull Request

# 5. After review & merge, cleanup
git checkout main
git pull origin main
git branch -d feature/my-feature
```

### 18.7 Commit Best Practices

#### DO ✅

```bash
# Atomic commits - satu perubahan per commit
git commit -m "feat: add user avatar upload"
git commit -m "test: add avatar upload tests"
git commit -m "docs: document avatar upload API"

# Descriptive subject line
git commit -m "fix(chat): prevent duplicate message submission on double-click"

# Use imperative mood
git commit -m "add feature"      # ✅
git commit -m "added feature"    # ❌
git commit -m "adds feature"     # ❌
```

#### DON'T ❌

```bash
# Vague messages
git commit -m "fix bug"
git commit -m "update code"
git commit -m "wip"
git commit -m "asdf"

# Too long subject (keep under 72 chars)
git commit -m "feat: add a new feature that allows users to export their chat history to multiple formats including PDF, JSON, and CSV with customizable options"

# Multiple unrelated changes in one commit
git commit -m "fix bug and add feature and update docs"
```

### 18.8 Pre-commit Checklist

Sebelum commit, pastikan:

```bash
# 1. Format PHP code
vendor/bin/pint --dirty

# 2. Lint JavaScript
npm run lint

# 3. Format JavaScript
npm run format

# 4. Run relevant tests
php artisan test --filter=MyFeature

# 5. Check types (optional)
npm run types
```

### 18.9 Commit Message Template

Buat file `.gitmessage` di root project:

```
# <type>(<scope>): <subject>
# |<----  Using a Maximum Of 50 Characters  ---->|

# [optional body]
# |<----   Try To Limit Each Line to a Maximum Of 72 Characters   ---->|

# [optional footer(s)]
# --- COMMIT END ---
# Type can be:
#   feat     - new feature
#   fix      - bug fix
#   docs     - documentation only
#   style    - formatting, no code change
#   refactor - refactoring code
#   perf     - performance improvement
#   test     - adding tests
#   build    - build system or dependencies
#   ci       - CI configuration
#   chore    - maintenance
#   revert   - revert previous commit
# --------------------
# Scope is optional and can be:
#   chat, auth, llm, mcp, ui, api, db, config
# --------------------
# Remember:
#   - Capitalize the subject line
#   - Use imperative mood ("add" not "added")
#   - Do not end subject line with a period
#   - Separate subject from body with blank line
```

Aktivasi:

```bash
git config commit.template .gitmessage
```

---

## 19. Known Issues & Technical Debt

### 19.1 Current Limitations

1. **No Message Edit/Delete**: User belum bisa edit atau delete individual messages
2. **No Chat Export**: Belum ada fitur export chat ke PDF/JSON
3. **No Multi-Modal**: Belum support image/file upload
4. **No Conversation Branching**: Tidak bisa branch conversation seperti ChatGPT

### 19.2 Technical Debt

1. **MCP Tool Testing**: Tool execution belum fully unit tested
2. **Error Handling**: Error messages belum user-friendly di beberapa cases
3. **Rate Limiting**: Belum ada rate limiting untuk API calls
4. **Caching**: Response caching belum diimplementasikan

### 19.3 Performance Considerations

1. **SQLite Limitation**: Untuk production dengan banyak user, pertimbangkan MySQL/PostgreSQL
2. **MCP HTTP Calls**: Setiap tool call adalah HTTP request, bisa di-optimize dengan socket
3. **Streaming Memory**: Long conversations bisa consume memory

---

## 20. Future Roadmap

### Phase 1 - Core Improvements

- [ ] Message edit/delete
- [ ] Chat search
- [ ] Export to PDF/JSON
- [ ] Rate limiting

### Phase 2 - Multi-Modal

- [ ] Image upload & analysis
- [ ] File attachment
- [ ] Voice input/output

### Phase 3 - Advanced Features

- [ ] Conversation branching
- [ ] Chat sharing
- [ ] Team workspaces
- [ ] Custom system prompts

### Phase 4 - Enterprise

- [ ] SSO integration
- [ ] Audit logging
- [ ] Usage analytics
- [ ] Self-hosted LLM support

---

## Contact & Resources

### Documentation

- [Laravel Docs](https://laravel.com/docs)
- [Inertia.js Docs](https://inertiajs.com)
- [Laravel MCP Docs](https://laravel.com/docs/mcp)
- [OpenAI API Docs](https://platform.openai.com/docs)
- [AWS Bedrock Docs](https://docs.aws.amazon.com/bedrock)

### Project Files

- `AGENTS.md` - Guidelines untuk AI coding assistants
- `CLAUDE.md` - Claude-specific instructions
- `GEMINI.md` - Gemini-specific instructions

### Related Documentation

- `docs/AWS_BEDROCK_STREAMING.md` - Custom Bedrock streaming implementation
- `docs/BEDROCK_APIS_EXPLAINED.md` - AWS Bedrock API explanation
- `docs/BEDROCK_IMPLEMENTATIONS_COMPARISON.md` - Comparison of approaches

---

_Document generated: January 2026_
_LaraChat version: 1.0_
