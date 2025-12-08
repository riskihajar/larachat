# Streaming Architecture

## Overview
This application showcases Laravel's streaming capabilities with React using Server-Sent Events (SSE) and the `@laravel/stream-react` package.

## Two Streaming Approaches

### 1. useStream Hook (Chat Messages)
Used for streaming AI chat responses from OpenAI.

#### Frontend Implementation
```typescript
import { useStream } from '@laravel/stream-react';

const { data, send, isStreaming } = useStream('/chat/stream');

// Send message with context
send({ messages: [...existingMessages, newMessage] });

// Display streaming response
{data && <div>{data}</div>}
```

#### Backend Implementation
```php
public function stream(Request $request)
{
    return response()->stream(function () use ($request) {
        $messages = $request->input('messages', []);
        
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

#### Key Features
- Accumulates streamed response in `data`
- `isStreaming` state for UI feedback
- Automatic reconnection on disconnect
- CSRF token handling via meta tag

### 2. useEventStream Hook (Title Generation)
Used for real-time chat title generation and updates.

#### Frontend Implementation
```typescript
import { useEventStream } from '@laravel/stream-react';

const { message } = useEventStream(`/chat/${chatId}/title-stream`, {
    eventName: "title-update",  // CRITICAL: Use 'eventName', not 'event'
    endSignal: "</stream>",
    onMessage: (event: MessageEvent) => {
        const parsed = JSON.parse(event.data);
        if (parsed.title) {
            onTitleUpdate(parsed.title);
        }
    },
    onComplete: () => {
        onComplete();
    },
    onError: (error) => {
        console.error('EventStream error:', error);
    },
});
```

#### Backend Implementation
```php
use Illuminate\Http\StreamedEvent;

public function titleStream(Chat $chat)
{
    return response()->eventStream(function () use ($chat) {
        // If title exists, send immediately
        if ($chat->title && $chat->title !== 'Untitled') {
            yield new StreamedEvent(
                event: 'title-update',
                data: json_encode(['title' => $chat->title])
            );
            return;
        }
        
        // Generate new title with OpenAI
        $firstMessage = $chat->messages()->where('type', 'prompt')->first();
        
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Generate a concise title (max 50 chars)...'
                ],
                ['role' => 'user', 'content' => $firstMessage->content]
            ],
        ]);

        $title = trim($response->choices[0]->message->content);
        $chat->update(['title' => $title]);

        yield new StreamedEvent(
            event: 'title-update',
            data: json_encode(['title' => $title])
        );
        
    }, endStreamWith: new StreamedEvent(
        event: 'title-update',
        data: '</stream>'
    ));
}
```

## Multiple Consumers Pattern
Multiple components can listen to the same EventStream:

```typescript
// Component 1: Updates main conversation title
<TitleGenerator 
    chatId={chat.id}
    onTitleUpdate={setConversationTitle}
    onComplete={() => setShouldGenerateTitle(false)}
/>

// Component 2: Updates sidebar chat list
<SidebarTitleUpdater
    chatId={chat.id}
    onComplete={() => setShouldUpdateSidebar(false)}
/>
```

## CSRF Protection
Unlike Inertia forms (which handle CSRF automatically), streaming endpoints require manual CSRF setup:

```html
<!-- In layout head -->
<meta name="csrf-token" content="{{ csrf_token() }}">
```

The `useStream` hook automatically reads this token for POST requests.

## Key Differences

| Feature | useStream | useEventStream |
|---------|-----------|----------------|
| Use Case | Continuous data stream | Named event updates |
| Response Type | Plain text/chunks | JSON events |
| Auto-accumulation | Yes (in `data`) | No (manual handling) |
| Event Types | Single stream | Multiple named events |
| End Signal | Connection close | Custom signal |

## Authentication Patterns

### Authenticated Users
```php
Route::middleware('auth')->group(function () {
    Route::post('/chat/stream', [ChatController::class, 'stream']);
    Route::get('/chat/{chat}/title-stream', [ChatController::class, 'titleStream']);
});
```

### Anonymous Users
```php
Route::post('/chat/stream', [ChatController::class, 'anonymousStream'])
    ->middleware('throttle:10,1');
```

## Performance Considerations
- Use `gpt-4o-mini` for title generation (faster, cheaper)
- Use `gpt-4` or `gpt-4-turbo` for main chat responses
- Throttle anonymous requests
- Set appropriate timeouts for streams
- Clean up EventStream listeners on component unmount

## Error Handling
```typescript
const { data, send, isStreaming, error } = useStream('/chat/stream', {
    onError: (err) => {
        console.error('Stream error:', err);
        // Show user-friendly error message
    }
});
```

## Testing Streaming Endpoints
```php
it('streams chat response', function () {
    $response = $this->post('/chat/stream', [
        'messages' => [['role' => 'user', 'content' => 'Hello']]
    ]);

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/event-stream');
});
```