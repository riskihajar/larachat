<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEmptyReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_home_with_no_empty_chats_creates_new_chat(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect();
        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);
        $this->assertEquals(1, Chat::where('user_id', $user->id)->count());
    }

    public function test_visiting_home_with_existing_empty_chat_reuses_it(): void
    {
        $user = User::factory()->create();
        $existingChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('chat.show', $existingChat));
        $response->assertSessionHas('info', 'Melanjutkan chat kosong');

        // Should not create new chat
        $this->assertEquals(1, Chat::where('user_id', $user->id)->count());
    }

    public function test_visiting_home_with_multiple_empty_chats_uses_newest(): void
    {
        $user = User::factory()->create();

        // Create older empty chat
        $olderChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
            'created_at' => now()->subHours(2),
        ]);

        // Create newer empty chat
        $newerChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get('/');

        // Should redirect to newer chat
        $response->assertRedirect(route('chat.show', $newerChat));
        $response->assertSessionHas('info', 'Melanjutkan chat kosong');
    }

    public function test_visiting_home_ignores_titled_chats(): void
    {
        $user = User::factory()->create();

        // Create chat with custom title (should be ignored)
        Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'My Custom Chat',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect();

        // Should create new Untitled chat, not reuse the titled one
        $this->assertEquals(2, Chat::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);
    }

    public function test_visiting_home_ignores_chats_with_messages(): void
    {
        $user = User::factory()->create();

        // Create Untitled chat but with messages (should be ignored)
        $chatWithMessages = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);
        Message::factory()->create([
            'chat_id' => $chatWithMessages->id,
            'type' => 'prompt',
            'content' => 'Hello',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect();

        // Should create new empty chat, not reuse the one with messages
        $this->assertEquals(2, Chat::where('user_id', $user->id)->count());

        // Verify the new chat is different from the existing one
        $newChat = Chat::where('user_id', $user->id)
            ->where('id', '!=', $chatWithMessages->id)
            ->first();
        $this->assertNotNull($newChat);
        $this->assertEquals('Untitled', $newChat->title);
    }

    public function test_creating_chat_without_message_reuses_empty_chat(): void
    {
        $user = User::factory()->create();

        $existingChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);

        $response = $this->actingAs($user)->post('/chat');

        $response->assertRedirect(route('chat.show', $existingChat));
        $response->assertSessionHas('info', 'Melanjutkan chat kosong');

        // Should not create new chat
        $this->assertEquals(1, Chat::where('user_id', $user->id)->count());
    }

    public function test_creating_chat_with_first_message_always_creates_new(): void
    {
        $user = User::factory()->create();

        // Create existing empty chat
        $existingChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);

        $response = $this->actingAs($user)->post('/chat', [
            'firstMessage' => 'Hello, this is a test message',
        ]);

        $response->assertRedirect();

        // Should create NEW chat, not reuse existing empty one
        $this->assertEquals(2, Chat::where('user_id', $user->id)->count());

        // Verify the new chat has the message
        $newChat = Chat::where('user_id', $user->id)
            ->where('id', '!=', $existingChat->id)
            ->first();
        $this->assertNotNull($newChat);
        $this->assertEquals(1, $newChat->messages()->count());
        $this->assertEquals('Hello, this is a test message', $newChat->messages()->first()->content);
    }

    public function test_empty_chat_definition_requires_both_untitled_and_no_messages(): void
    {
        $user = User::factory()->create();

        // Create chat that's Untitled but has messages
        $untitledWithMessages = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);
        Message::factory()->create([
            'chat_id' => $untitledWithMessages->id,
            'type' => 'prompt',
            'content' => 'Test',
        ]);

        // Create chat with custom title but no messages
        $titledNoMessages = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'My Chat',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect();

        // Should create new chat because neither existing chat qualifies as "empty"
        $this->assertEquals(3, Chat::where('user_id', $user->id)->count());
    }

    public function test_empty_chat_reuse_respects_user_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create empty chat for user2
        $user2Chat = Chat::factory()->create([
            'user_id' => $user2->id,
            'title' => 'Untitled',
        ]);

        $response = $this->actingAs($user1)->get('/');

        $response->assertRedirect();

        // Should create new chat for user1, not reuse user2's chat
        $this->assertEquals(1, Chat::where('user_id', $user1->id)->count());
        $this->assertEquals(1, Chat::where('user_id', $user2->id)->count());
    }

    public function test_creating_chat_with_custom_provider_uses_that_provider(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/chat', [
            'provider' => 'bedrock',
        ]);

        $response->assertRedirect();

        $chat = Chat::where('user_id', $user->id)->first();
        $this->assertNotNull($chat);
        $this->assertEquals('bedrock', $chat->provider);
    }

    public function test_creating_chat_with_custom_title_creates_new_chat(): void
    {
        $user = User::factory()->create();

        // Create existing empty chat
        $existingChat = Chat::factory()->create([
            'user_id' => $user->id,
            'title' => 'Untitled',
        ]);

        // Create chat with custom title
        $response = $this->actingAs($user)->post('/chat', [
            'title' => 'My Custom Chat',
        ]);

        $response->assertRedirect();

        // Should create NEW chat with custom title, not reuse existing
        $this->assertEquals(2, Chat::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'title' => 'My Custom Chat',
        ]);
    }
}
