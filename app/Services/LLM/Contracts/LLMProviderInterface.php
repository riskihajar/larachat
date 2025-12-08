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
}
