<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements LLMProviderInterface
{
    protected string $model;

    protected string $titleModel;

    public function __construct(?string $model = null)
    {
        $this->model = $model ?? config('llm.openai.model', 'gpt-4o');
        $this->titleModel = config('llm.openai.title_model', 'gpt-4o-mini');
    }

    public function stream(array $messages): \Generator
    {
        // Check if in testing environment or API key not set
        if (app()->environment('testing') || ! config('openai.api_key')) {
            yield 'This is a test response.';

            return;
        }

        try {
            // Format messages for OpenAI
            $openAIMessages = $this->formatMessages($messages);

            $stream = OpenAI::chat()->createStreamed([
                'model' => $this->model,
                'messages' => $openAIMessages,
            ]);

            foreach ($stream as $response) {
                $chunk = $response->choices[0]->delta->content;
                if ($chunk !== null) {
                    yield $chunk;
                }
            }
        } catch (\Exception $e) {
            Log::error('OpenAI streaming error', [
                'message' => $e->getMessage(),
            ]);
            yield 'Error: Unable to generate response.';
        }
    }

    public function generateTitle(string $firstMessage): string
    {
        if (app()->environment('testing') || ! config('openai.api_key')) {
            return 'Chat about: '.substr($firstMessage, 0, 30);
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->titleModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Generate a concise, descriptive title (max 50 characters) for a chat that starts with the following message. Respond with only the title, no quotes or extra formatting.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $firstMessage,
                    ],
                ],
                'max_tokens' => 20,
                'temperature' => 0.7,
            ]);

            $title = trim($response->choices[0]->message->content);

            // Ensure title length
            if (strlen($title) > 50) {
                $title = substr($title, 0, 47).'...';
            }

            return $title;
        } catch (\Exception $e) {
            Log::error('OpenAI title generation error', [
                'message' => $e->getMessage(),
            ]);

            return substr($firstMessage, 0, 47).'...';
        }
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Format messages for OpenAI API.
     */
    protected function formatMessages(array $messages): array
    {
        return collect($messages)
            ->map(function ($message) {
                // Get role from either 'role' or 'type' field
                $role = $message['role'] ?? $message['type'] ?? null;

                if (! $role) {
                    return null;
                }

                // Map 'prompt' to 'user' and 'response' to 'assistant'
                $mappedRole = match ($role) {
                    'prompt', 'user' => 'user',
                    'response', 'assistant' => 'assistant',
                    'system' => 'system',
                    default => $role,
                };

                return [
                    'role' => $mappedRole,
                    'content' => $message['content'],
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
