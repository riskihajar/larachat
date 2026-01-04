<?php

namespace App\Services\LLM\Contracts;

interface LLMProviderInterface
{
    /**
     * Stream a chat response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return \Generator<string>
     */
    public function stream(array $messages): \Generator;

    /**
     * Stream a chat response with tool calling support.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $userContext
     * @return \Generator<string>
     */
    public function streamWithTools(array $messages, array $userContext = []): \Generator;

    /**
     * Generate a chat title from messages.
     */
    public function generateTitle(string $firstMessage): string;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get the model being used.
     */
    public function getModel(): string;

    /**
     * Enable tool calling support (optional method).
     * 
     * @return static
     */
    public function withTools(): self;
}
