<?php

namespace App\Services\LLM\Providers;

use App\Services\LLM\Contracts\LLMProviderInterface;
use App\Services\ToolCoordinator;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements LLMProviderInterface
{
    protected string $model;

    protected string $titleModel;

    protected ?ToolCoordinator $toolCoordinator = null;

    public function __construct(?string $model = null)
    {
        $this->model = $model ?? config('llm.openai.model', 'gpt-4o');
        $this->titleModel = config('llm.openai.title_model', 'gpt-4o-mini');
    }

    /**
     * Enable tool calling support.
     */
    public function withTools(): self
    {
        $this->toolCoordinator = new ToolCoordinator();
        return $this;
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
     * Stream response with function calling support.
     */
    public function streamWithTools(array $messages, array $userContext = []): \Generator
    {
        // Check if tools are enabled
        if (!$this->toolCoordinator) {
            yield from $this->stream($messages);
            return;
        }

        // Check if in testing environment or API key not set
        if (app()->environment('testing') || ! config('openai.api_key')) {
            yield 'This is a test response with tools.';
            return;
        }

        try {
            // Get the latest user message
            $latestMessage = end($messages);
            $userMessage = $latestMessage['content'] ?? '';
            $userTimezone = $userContext['timezone'] ?? 'Asia/Makassar';

            // Check if message needs tool calling
            $toolRequest = $this->toolCoordinator->processMessage($userMessage, $userTimezone);

            if (!$toolRequest['needs_tool']) {
                // Normal chat without tools
                yield from $this->stream($messages);
                return;
            }

            // Execute tool first
            $toolResult = $this->toolCoordinator->executeTool(
                $toolRequest['tool_name'],
                $toolRequest['arguments'],
                $toolRequest['timezone']
            );

            // Add tool result to context for OpenAI
            $messages[] = [
                'role' => 'system',
                'content' => "Hasil tool {$toolRequest['tool_name']}: {$toolResult['formatted_response']}"
            ];

            // Format messages for OpenAI
            $openAIMessages = $this->formatMessages($messages);

            // Add function schemas to the first system message
            $functionSchemas = $this->toolCoordinator->getFunctionSchemas();
            
            $stream = OpenAI::chat()->createStreamed([
                'model' => $this->model,
                'messages' => $openAIMessages,
                'tools' => $functionSchemas,
                'tool_choice' => 'auto',
            ]);

            foreach ($stream as $response) {
                // Handle tool calls
                if (isset($response->choices[0]->delta->tool_calls)) {
                    $toolCall = $response->choices[0]->delta->tool_calls[0] ?? null;
                    if ($toolCall && $toolCall->id) {
                        yield "🔧 Menggunakan tool {$toolCall->function->name}...";
                        continue;
                    }
                }

                // Handle regular content
                $chunk = $response->choices[0]->delta->content;
                if ($chunk !== null) {
                    yield $chunk;
                }
            }

        } catch (\Exception $e) {
            Log::error('OpenAI streaming with tools error', [
                'message' => $e->getMessage(),
            ]);
            yield 'Error: Unable to generate response with tools.';
        }
    }

    /**
     * Handle function call execution.
     */
    private function executeFunctionCall(string $functionName, array $arguments, string $userTimezone = 'Asia/Makassar'): array
    {
        if (!$this->toolCoordinator) {
            throw new \Exception('Tool coordinator not initialized');
        }

        // Map OpenAI function names to MCP tool names
        $functionMap = [
            'get_current_datetime' => 'get-current-date-time-tool',
            'convert_timezone' => 'convert-timezone-tool',
            'get_timezone_info' => 'get-timezone-info-tool',
            'list_timezones' => 'list-timezones-tool',
        ];

        $mcpToolName = $functionMap[$functionName] ?? null;
        
        if (!$mcpToolName) {
            throw new \Exception("Unknown function: {$functionName}");
        }

        return $this->toolCoordinator->executeTool($mcpToolName, $arguments, $userTimezone);
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
