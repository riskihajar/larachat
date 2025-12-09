<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\LLM\LLMProviderFactory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        // For authenticated users, find existing empty chat or create new one
        if (Auth::check()) {
            $chat = $this->findOrCreateEmptyChat();

            return redirect()->route('chat.show', $chat);
        }

        // For unauthenticated users, show the blank chat page
        return Inertia::render('chat', [
            'chat' => null,
            'availableProviders' => LLMProviderFactory::getAvailableProviders(),
        ]);
    }

    public function show(Chat $chat)
    {
        $this->authorize('view', $chat);

        $chat->load('messages');

        return Inertia::render('chat', [
            'chat' => $chat,
            'availableProviders' => LLMProviderFactory::getAvailableProviders(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'firstMessage' => 'nullable|string',
            'provider' => 'nullable|string|in:openai,bedrock',
        ]);

        // If firstMessage provided, always create new chat (user is starting conversation)
        if ($request->firstMessage) {
            $title = $request->title ?? 'Untitled';

            $chat = Auth::user()->chats()->create([
                'title' => $title,
                'provider' => $request->provider ?? config('llm.default'),
            ]);

            // Save the first message
            $chat->messages()->create([
                'type' => 'prompt',
                'content' => $request->firstMessage,
            ]);

            return redirect()->route('chat.show', $chat)->with('stream', true);
        }

        // If custom title provided (not "Untitled"), create new chat with that title
        if ($request->title && $request->title !== 'Untitled') {
            $chat = Auth::user()->chats()->create([
                'title' => $request->title,
                'provider' => $request->provider ?? config('llm.default'),
            ]);

            return redirect()->route('chat.show', $chat);
        }

        // If no custom title or firstMessage, find existing empty chat or create new one
        $chat = $this->findOrCreateEmptyChat($request->provider);

        return redirect()->route('chat.show', $chat);
    }

    public function update(Request $request, Chat $chat)
    {
        $this->authorize('update', $chat);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $chat->update([
            'title' => $request->title,
        ]);

        return redirect()->back();
    }

    public function destroy(Chat $chat)
    {
        $this->authorize('delete', $chat);

        $chatId = $chat->id;
        $chat->delete();

        // Check if this is the current chat being viewed
        $currentUrl = request()->header('Referer') ?? '';
        $isCurrentChat = str_contains($currentUrl, "/chat/{$chatId}");

        if ($isCurrentChat) {
            // If deleting the current chat, redirect to home
            return redirect()->route('home');
        } else {
            // If deleting from sidebar, redirect back to current page
            return redirect()->back();
        }
    }

    public function stream(Request $request, ?Chat $chat = null)
    {
        if ($chat) {
            $this->authorize('view', $chat);
        }

        // Determine which provider to use
        $providerName = $request->input('provider') ?? ($chat?->provider ?? config('llm.default'));
        $provider = LLMProviderFactory::make($providerName);

        return response()->stream(function () use ($request, $chat, $provider) {
            // Disable ALL output buffering layers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Set no timeout for streaming
            set_time_limit(0);

            $messages = $request->input('messages', []);

            if (empty($messages)) {
                return;
            }

            // Only save messages if we have an existing chat (authenticated user with saved chat)
            if ($chat) {
                foreach ($messages as $message) {
                    // Only save if message doesn't have an ID (not from database)
                    if (! isset($message['id'])) {
                        $chat->messages()->create([
                            'type' => $message['type'],
                            'content' => $message['content'],
                        ]);
                    }
                }
            }

            // Stream response using provider
            $fullResponse = '';

            try {
                foreach ($provider->stream($messages) as $chunk) {
                    $fullResponse .= $chunk;

                    // Send chunk immediately
                    echo $chunk;

                    // Aggressive flushing
                    @ob_flush();
                    @flush();
                }
            } catch (\Exception $e) {
                \Log::error('LLM streaming error', [
                    'provider' => $provider->getName(),
                    'error' => $e->getMessage(),
                ]);
                $errorMessage = 'Error: Unable to generate response.';
                $fullResponse .= $errorMessage;
                echo $errorMessage;

                @ob_flush();
                @flush();
            }

            // Save the AI response to database if authenticated
            if ($chat && $fullResponse) {
                $chat->messages()->create([
                    'type' => 'response',
                    'content' => $fullResponse,
                ]);

                // Generate title if this is a new chat with "Untitled" title
                \Log::info('Checking if should generate title', ['chat_title' => $chat->title]);
                if ($chat->title === 'Untitled') {
                    \Log::info('Generating title in background for chat', ['chat_id' => $chat->id]);
                    $this->generateTitleInBackground($chat);
                } else {
                    \Log::info('Not generating title', ['current_title' => $chat->title]);
                }
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function generateChatTitle(array $messages): string
    {
        $firstPrompt = collect($messages)
            ->where('type', 'prompt')
            ->first();

        if ($firstPrompt) {
            return substr($firstPrompt['content'], 0, 50).'...';
        }

        return 'New Chat';
    }

    public function titleStream(Chat $chat)
    {
        $this->authorize('view', $chat);

        \Log::info('Title stream requested for chat', ['chat_id' => $chat->id, 'title' => $chat->title]);

        return response()->eventStream(function () use ($chat) {
            // If title is already set and not "Untitled", send it immediately
            if ($chat->title && $chat->title !== 'Untitled') {
                yield new StreamedEvent(
                    event: 'title-update',
                    data: json_encode(['title' => $chat->title])
                );

                return;
            }

            // Generate title immediately when stream is requested
            $this->generateTitleInBackground($chat);

            // Wait for title updates and stream them
            $startTime = time();
            $timeout = 30; // 30 second timeout

            while (time() - $startTime < $timeout) {
                // Refresh the chat model to get latest title
                $chat->refresh();

                // If title has changed from "Untitled", send it
                if ($chat->title !== 'Untitled') {
                    yield new StreamedEvent(
                        event: 'title-update',
                        data: json_encode(['title' => $chat->title])
                    );
                    break;
                }

                // Wait a bit before checking again
                usleep(500000); // 0.5 seconds
            }
        }, endStreamWith: new StreamedEvent(event: 'title-update', data: '</stream>'));
    }

    private function generateTitleInBackground(Chat $chat)
    {
        // Get the first message
        $firstMessage = $chat->messages()->where('type', 'prompt')->first();

        if (! $firstMessage) {
            return;
        }

        try {
            // Use the chat's provider to generate title
            $provider = LLMProviderFactory::make($chat->provider);
            $generatedTitle = $provider->generateTitle($firstMessage->content);

            // Update the chat title
            $chat->update(['title' => $generatedTitle]);

            \Log::info('Generated title for chat', [
                'chat_id' => $chat->id,
                'title' => $generatedTitle,
                'provider' => $chat->provider,
            ]);
        } catch (\Exception $e) {
            // Fallback title on error
            $fallbackTitle = substr($firstMessage->content, 0, 47).'...';
            $chat->update(['title' => $fallbackTitle]);
            \Log::error('Error generating title, using fallback', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Find existing empty chat or create new one.
     * Empty chat is defined as: title = 'Untitled' AND no messages.
     * Returns the newest empty chat if found, otherwise creates new chat.
     */
    private function findOrCreateEmptyChat(?string $provider = null): Chat
    {
        // Look for existing empty chat (Untitled with no messages)
        $emptyChat = Auth::user()->chats()
            ->where('title', 'Untitled')
            ->whereDoesntHave('messages')
            ->latest()
            ->first();

        if ($emptyChat) {
            // Flash message to notify user
            session()->flash('info', 'Melanjutkan chat kosong');

            return $emptyChat;
        }

        // No empty chat found, create new one
        return Auth::user()->chats()->create([
            'title' => 'Untitled',
            'provider' => $provider ?? config('llm.default'),
        ]);
    }
}
