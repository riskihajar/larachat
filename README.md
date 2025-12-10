# Laravel Chat Demo with useStream

A real-time chat application demonstrating the power of Laravel's `useStream` hook for React applications. This demo showcases how to build a ChatGPT-like interface with streaming responses, message persistence, and authentication support.

## Video Tutorial

Watch the complete tutorial on YouTube:

[![Building an AI Chat App with Laravel and React useStream](https://img.youtube.com/vi/BuUbTRHuvAw/maxresdefault.jpg)](https://youtu.be/BuUbTRHuvAw)

🎥 **[Watch on YouTube: Building an AI Chat App with Laravel and React useStream](https://youtu.be/BuUbTRHuvAw)**

## Features

- 🚀 Real-time streaming responses using Server-Sent Events (SSE)
- 💬 Modern chat interface with shadcn/ui InputGroup components
- 🔐 Optional authentication with message persistence
- 🎯 Automatic chat title generation using `useEventStream`
- 🎨 Beautiful UI with Tailwind CSS v4 and shadcn/ui
- 📱 Responsive design with mobile support
- 🌓 Dark/light mode with system preference detection
- 🔄 **Multi-provider & multi-model support**: OpenAI (GPT-4o, GPT-4 Turbo, GPT-3.5) and AWS Bedrock (Claude Sonnet 4.5, Claude Sonnet 3.7, Claude Haiku 3.5)
- ⚡ **Custom AWS Bedrock streaming**: Real-time word-by-word streaming with binary event stream parser
- 🎛️ **Per-chat model selection**: Choose different AI models for each conversation
- 🔢 **Character counter**: Real-time character count display
- ⏹️ **Stop generation**: Cancel streaming responses mid-generation
- ⌨️ **Keyboard shortcuts**: Enter to send, Shift+Enter for new line
- 📝 **Multi-line input**: Auto-resizing textarea with modern input group design
- 🗂️ **ULID-based IDs**: All database records use ULIDs for better sortability and security

## System Requirements

Before getting started, ensure your system meets these requirements:

### Required
- **PHP 8.2 or higher** with the following extensions:
  - curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, session, tokenizer, xml
- **Node.js 22 or higher** (for React 19 support)
- **Composer 2.x**
- **SQLite** (default database, or MySQL/PostgreSQL if preferred)
- **Git** (for cloning the repository)

### Optional but Recommended
- **OpenAI API Key** (for GPT models)
- **AWS Bedrock credentials** (for Claude models via AWS Bedrock)
- **PHP development server** or **Laravel Valet** for local development

### Framework Versions Used
- **Laravel 12.0** (latest)
- **React 19** (latest)
- **Tailwind CSS v4** (beta)
- **Inertia.js 2.0**

> **Note**: This demo uses cutting-edge versions to showcase the latest features. If you encounter compatibility issues, check the versions above against your local environment.

## Quick Start

1. Clone the repository and install dependencies:

```bash
composer install
npm install
```

2. Set up your environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure your AI provider credentials in `.env`:

**For OpenAI (GPT models):**
```env
OPENAI_API_KEY=your-api-key-here
```

**For AWS Bedrock (Claude models):**
```env
BEDROCK_AWS_ACCESS_KEY_ID=your-access-key
BEDROCK_AWS_SECRET_ACCESS_KEY=your-secret-key
BEDROCK_AWS_DEFAULT_REGION=us-west-2
BEDROCK_MODEL=anthropic.claude-sonnet-4-20250514-v1:0
```

**Set default provider:**
```env
LLM_DEFAULT_PROVIDER=openai  # or 'bedrock'
```

4. Run migrations and start the development server:

```bash
php artisan migrate
composer dev
```

> **Note**: The `composer dev` command runs multiple processes concurrently (server, queue, logs, and Vite). If you encounter issues, run each command separately in different terminals:
> ```bash
> # Terminal 1: Laravel server
> php artisan serve
> 
> # Terminal 2: Queue worker (for background jobs)
> php artisan queue:listen
> 
> # Terminal 3: Vite development server
> npm run dev
> ```

## Troubleshooting

### Common Setup Issues

**"Node.js version too old" error:**
- Ensure you have Node.js 22+ installed
- Use `nvm` to manage Node.js versions: `nvm install 22 && nvm use 22`

**"Class 'OpenAI' not found" error:**
- Run `composer install` to ensure all PHP dependencies are installed
- Check that your `OPENAI_API_KEY` is set in `.env` (or leave it empty for mock responses)

**Database connection errors:**
- The default setup uses SQLite - ensure the `database/database.sqlite` file exists
- If it's missing, create it with: `touch database/database.sqlite`
- Then run: `php artisan migrate`

**Vite build errors with Tailwind CSS v4:**
- Clear your npm cache: `npm cache clean --force`
- Delete `node_modules` and reinstall: `rm -rf node_modules && npm install`
- Ensure you're using Node.js 22+

**"CSRF token mismatch" for streaming:**
- Ensure the CSRF meta tag is present in your layout (already included in this demo)
- Clear browser cache and cookies for the local development domain

## Using the useStream Hook

The `useStream` hook from `@laravel/stream-react` makes it incredibly simple to consume streamed responses in your React application. Here's how this demo implements it:

### Basic Chat Implementation

```tsx
import { useStream } from '@laravel/stream-react';

function Chat() {
    const [messages, setMessages] = useState([]);
    const { data, send, isStreaming } = useStream('/chat/stream');

    const handleSubmit = (e) => {
        e.preventDefault();
        const query = e.target.query.value;

        // Add user message to local state
        const newMessage = { type: 'prompt', content: query };
        setMessages([...messages, newMessage]);

        // Send all messages to the stream
        send({ messages: [...messages, newMessage] });
        
        e.target.reset();
    };

    return (
        <div>
            {/* Display messages */}
            {messages.map((msg, i) => (
                <div key={i}>{msg.content}</div>
            ))}
            
            {/* Show streaming response */}
            {data && <div>{data}</div>}
            
            {/* Input form */}
            <form onSubmit={handleSubmit}>
                <input name="query" disabled={isStreaming} />
                <button type="submit">Send</button>
            </form>
        </div>
    );
}
```

### Key Concepts

1. **Stream URL**: The hook connects to your Laravel endpoint that returns a streamed response
2. **Sending Data**: The `send` method posts JSON data to your stream endpoint
3. **Streaming State**: Use `isStreaming` to show loading indicators or disable inputs
4. **Response Accumulation**: The `data` value automatically accumulates the streamed response

### Backend Stream Endpoint

On the Laravel side, create a streaming endpoint:

```php
public function stream(Request $request)
{
    return response()->stream(function () use ($request) {
        $messages = $request->input('messages', []);
        
        // Stream response from OpenAI
        $stream = OpenAI::chat()->createStreamed([
            'model' => 'gpt-4',
            'messages' => $messages,
        ]);

        foreach ($stream as $response) {
            $chunk = $response->choices[0]->delta->content;
            if ($chunk !== null) {
                echo $chunk;
                ob_flush();
                flush();
            }
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

### Using the useEventStream Hook

This demo showcases `useEventStream` for real-time updates. When you create a new chat, it initially shows "Untitled" but automatically generates a proper title using OpenAI and streams it back in real-time.

#### Key Implementation Details

The critical configuration for `useEventStream` is using `eventName` (not `event`) and handling the `MessageEvent` properly:

```tsx
import { useEventStream } from '@laravel/stream-react';

function TitleGenerator({ chatId, onTitleUpdate, onComplete }) {
    const { message } = useEventStream(`/chat/${chatId}/title-stream`, {
        eventName: "title-update",  // Use 'eventName', not 'event'
        endSignal: "</stream>",
        onMessage: (event) => {      // Receives MessageEvent object
            try {
                const parsed = JSON.parse(event.data);
                if (parsed.title) {
                    onTitleUpdate(parsed.title);
                }
            } catch (error) {
                console.error('Error parsing title:', error);
            }
        },
        onComplete: () => {
            onComplete();
        },
        onError: (error) => {
            console.error('EventStream error:', error);
            onComplete();
        },
    });

    return null; // This is a listener component
}
```

#### Multiple EventStream Consumers

You can have multiple components listening to the same EventStream for different purposes:

```tsx
// Component 1: Updates conversation title
<TitleGenerator 
    chatId={chat.id}
    onTitleUpdate={setConversationTitle}
    onComplete={() => setShouldGenerateTitle(false)}
/>

// Component 2: Updates sidebar 
<SidebarTitleUpdater
    chatId={chat.id} 
    onComplete={() => setShouldUpdateSidebar(false)}
/>
```

### Backend EventStream Implementation

The Laravel backend uses `response()->eventStream()` to generate and stream title updates:

```php
use Illuminate\Http\StreamedEvent;

public function titleStream(Chat $chat)
{
    $this->authorize('view', $chat);

    return response()->eventStream(function () use ($chat) {
        // If title already exists, send it immediately
        if ($chat->title && $chat->title !== 'Untitled') {
            yield new StreamedEvent(
                event: 'title-update',
                data: json_encode(['title' => $chat->title])
            );
            return;
        }
        
        // Generate title using OpenAI
        $firstMessage = $chat->messages()->where('type', 'prompt')->first();
        
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system', 
                    'content' => 'Generate a concise, descriptive title (max 50 characters) for a chat that starts with the following message. Respond with only the title, no quotes or extra formatting.'
                ],
                ['role' => 'user', 'content' => $firstMessage->content]
            ],
            'max_tokens' => 20,
            'temperature' => 0.7,
        ]);

        $title = trim($response->choices[0]->message->content);
        $chat->update(['title' => $title]);

        // Stream the new title
        yield new StreamedEvent(
            event: 'title-update',
            data: json_encode(['title' => $title])
        );
        
    }, endStreamWith: new StreamedEvent(event: 'title-update', data: '</stream>'));
}
```

#### EventStream Route Configuration

```php
Route::middleware('auth')->group(function () {
    Route::get('/chat/{chat}/title-stream', [ChatController::class, 'titleStream'])
        ->name('chat.title.stream');
});
```

#### How It Works

1. **User sends first message** → AI response streams back via `useStream`
2. **Response completes** → Triggers EventStream for title generation  
3. **Server generates title** → Uses OpenAI to create descriptive title
4. **EventStream sends update** → Both conversation header and sidebar update in real-time
5. **Components unmount** → Clean up after receiving title

This creates a seamless experience where users see titles generated and updated live without any page refreshes.

### Advanced Features in This Demo

- **Authentication Support**: Authenticated users get their chats persisted to the database
- **Dynamic Routing**: Different stream URLs for authenticated vs anonymous users
- **Message Persistence**: Completed responses are added to the message history
- **Real-time Title Generation**: Event streams automatically update chat titles
- **Error Handling**: Graceful fallbacks for API failures

## Multi-Provider & Multi-Model Architecture

This fork extends the original demo with support for multiple AI providers and per-chat model selection through a clean, interface-based architecture.

### Model Selector

Users can select their preferred AI model for each conversation through an elegant dropdown interface:

```tsx
import { InputGroup, InputGroupAddon, InputGroupTextarea } from '@/components/ui/input-group';
import { DropdownMenu } from '@/components/ui/dropdown-menu';

// Modern chat input with inline model selector
<InputGroup>
    <InputGroupTextarea placeholder="Ask, Search or Chat..." />
    <InputGroupAddon align="block-end">
        <DropdownMenu>
            <DropdownMenuTrigger>
                AWS Bedrock: Claude Sonnet 4.5
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                {/* All available models grouped by provider */}
            </DropdownMenuContent>
        </DropdownMenu>
        <InputGroupText>52 chars</InputGroupText>
        <InputGroupButton>Send</InputGroupButton>
    </InputGroupAddon>
</InputGroup>
```

### Supported Providers & Models

| Provider | Available Models | Streaming |
|----------|-----------------|-----------|
| **OpenAI** | GPT-4o, GPT-4o Mini, GPT-4 Turbo, GPT-4, GPT-3.5 Turbo | ✅ Native SDK |
| **AWS Bedrock** | Claude Sonnet 4.5, Claude Sonnet 3.7, Claude Sonnet 3.5, Claude Haiku 3.5 | ✅ Custom Binary Parser |

### AWS Bedrock Implementation

AWS Bedrock required a custom streaming implementation due to the AWS SDK PHP's buffering limitation. The solution:

1. **Direct HTTP Streaming** - Bypasses AWS SDK using Guzzle with `stream => true`
2. **Binary Event Stream Parser** - Implements AWS event stream encoding specification
3. **Base64 Payload Decoding** - Extracts and decodes the `bytes` field from AWS payloads
4. **Progressive Yielding** - Each chunk is sent to the browser immediately

**Key Technical Achievement:**
```php
// AWS returns base64-encoded JSON in binary event stream
$chunk = json_decode($payload, true);
if (isset($chunk['bytes'])) {
    $decodedPayload = base64_decode($chunk['bytes']);
    $chunk = json_decode($decodedPayload, true);
    // Now we have: {"type": "content_block_delta", "delta": {"text": "..."}}
}
```

For detailed implementation guide, see **[docs/AWS_BEDROCK_STREAMING.md](docs/AWS_BEDROCK_STREAMING.md)**

### Model Selection Storage

Each chat stores both the provider and specific model being used:

```php
// Database schema
Schema::create('chats', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('provider'); // 'openai' or 'bedrock'
    $table->string('model');    // Specific model ID
    $table->timestamps();
});
```

The model field stores the full model identifier (e.g., `us.anthropic.claude-sonnet-4-20250514-v1:0` for AWS Bedrock or `gpt-4o` for OpenAI), allowing precise model tracking per conversation.

### Adding New Providers

To add a new LLM provider:

1. Create a class implementing `LLMProviderInterface`:
```php
namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;

class YourProvider implements LLMProviderInterface
{
    protected string $model;

    public function __construct(?string $model = null)
    {
        $this->model = $model ?? config('llm.your-provider.model');
    }

    public function stream(array $messages): \Generator
    {
        // Implement streaming logic for your provider
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    public function generateTitle(string $firstMessage): string
    {
        // Generate chat title using your provider
        return 'Generated Title';
    }

    public function getName(): string
    {
        return 'your-provider';
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
```

2. Register in `LLMProviderFactory`:
```php
public static function make(?string $provider = null, ?string $model = null): LLMProviderInterface
{
    $provider = $provider ?? config('llm.default');
    $model = $model ?? config("llm.default_models.{$provider}");

    return match ($provider) {
        'openai' => new OpenAIProvider($model),
        'bedrock' => new BedrockProvider($model),
        'your-provider' => new YourProvider($model),
        default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
    };
}
```

3. Add configuration to `config/llm.php`:
```php
'providers' => [
    'openai' => 'OpenAI',
    'bedrock' => 'AWS Bedrock',
    'your-provider' => 'Your Provider',
],

'models' => [
    'your-provider' => [
        'model-1' => 'Model 1 Display Name',
        'model-2' => 'Model 2 Display Name',
    ],
],

'default_models' => [
    'your-provider' => 'model-1',
],
```

4. Models will automatically appear in the UI dropdown grouped by provider

## Project Structure

```
app/
├── Concerns/
│   └── HasUlids.php                # ULID trait for models
├── Models/
│   ├── Chat.php                    # Chat model with ULID
│   ├── Message.php                 # Message model with ULID
│   ├── User.php                    # User model with ULID
│   ├── Role.php                    # Custom role model for Spatie
│   └── Permission.php              # Custom permission model for Spatie
└── Services/LLM/
    ├── Contracts/
    │   └── LLMProviderInterface.php    # Provider interface
    ├── Providers/
    │   ├── OpenAIProvider.php          # OpenAI implementation
    │   └── BedrockProvider.php         # AWS Bedrock implementation
    └── LLMProviderFactory.php          # Provider factory with model support

resources/js/
├── pages/
│   └── chat.tsx                    # Main chat with modern InputGroup UI
├── components/
│   ├── conversation.tsx            # Message display
│   ├── title-generator.tsx         # Real-time title generation
│   ├── sidebar-title-updater.tsx   # Sidebar sync
│   └── ui/
│       ├── input-group.tsx         # shadcn InputGroup component
│       ├── textarea.tsx            # shadcn Textarea component
│       ├── dropdown-menu.tsx       # shadcn Dropdown component
│       ├── separator.tsx           # shadcn Separator component
│       └── ...                     # Other shadcn/ui components
└── layouts/
    └── app-layout.tsx              # Main application layout

database/migrations/
├── 0001_01_01_000000_create_users_table.php        # Users with ULID
├── 2025_05_28_173049_create_chats_table.php        # Chats with ULID
├── 2025_05_28_173221_create_messages_table.php     # Messages with ULID
├── 2025_12_09_021430_create_permission_tables.php  # Permissions with ULID
└── 2025_12_10_035518_add_model_to_chats_table.php  # Model field addition

docs/
└── AWS_BEDROCK_STREAMING.md        # Detailed AWS implementation guide
```

## Why useStream Needs CSRF Tokens (Even with Inertia)

If you're familiar with Inertia.js, you might wonder why we need to handle CSRF tokens manually when using `useStream`. Here's the key distinction:

### Inertia Forms vs Stream Endpoints

**Inertia Forms** use the `useForm` helper:
```tsx
// Standard Inertia approach - CSRF handled automatically
const form = useForm({ message: '' });
form.post('/chat'); // Returns an Inertia response
```

**Stream Endpoints** require manual CSRF handling:
```tsx
// Streaming approach - needs CSRF token
const { send } = useStream('/chat/stream'); // This is a POST to an API endpoint
```

### Why the Difference?

1. **Different Response Types**: Inertia expects a page component response, while streaming endpoints return Server-Sent Events (SSE)
2. **Direct API Calls**: The `useStream` hook makes direct POST requests to your endpoint, bypassing Inertia's request lifecycle
3. **No Automatic CSRF**: Since it's not an Inertia request, CSRF tokens aren't automatically included

### Setting Up CSRF for Streams

Add the CSRF meta tag to your layout:
```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

The `useStream` hook automatically reads this token, or you can provide it explicitly:
```tsx
const { send } = useStream('/chat/stream', {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
});
```

This separation actually gives you more flexibility - you can have both traditional Inertia pages and real-time streaming features in the same application!

## Learn More

### Official Resources
- [Laravel Stream Documentation](https://github.com/laravel/stream)
- [Server-Sent Events in Laravel](https://laravel.com/docs/responses#event-streams)
- [OpenAI PHP Client](https://github.com/openai-php/client)

### Multi-Provider Implementation
- [Prism by Echo Labs](https://prism.echolabs.dev/) - Laravel package for AI integration (supports multiple providers)
- [AWS Bedrock Documentation](https://docs.aws.amazon.com/bedrock/)
- [AWS Event Stream Encoding](https://docs.aws.amazon.com/lexv2/latest/dg/event-stream-encoding.html)

### This Fork's Documentation
- **[AWS_BEDROCK_STREAMING.md](docs/AWS_BEDROCK_STREAMING.md)** - Complete guide to AWS Bedrock streaming implementation

## Credits

### Original Project
This is a fork of [Laravel Chat Demo](https://github.com/laravel/larachat) by the Laravel team, which demonstrates the `useStream` hook for React applications.

### Multi-Provider & UI Enhancements
Multi-provider architecture, modern UI components, and ULID implementation by [@riskihajar](https://github.com/riskihajar).

**Key contributions:**
- Interface-based provider abstraction with per-chat model selection
- AWS Bedrock binary event stream parser for real-time streaming
- Modern chat interface with shadcn/ui InputGroup components
- ULID-based database architecture for all models
- Character counter and stop generation functionality
- Keyboard shortcuts (Enter/Shift+Enter) support
- Multi-line auto-resizing textarea input
- Spatie Permission package integration with ULID support
- Comprehensive AWS Bedrock streaming documentation

## License

This demo is open-sourced software licensed under the [MIT license](LICENSE.md).