import Conversation from '@/components/conversation';
import SidebarTitleUpdater from '@/components/sidebar-title-updater';
import TitleGenerator from '@/components/title-generator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupText, InputGroupTextarea } from '@/components/ui/input-group';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useStream } from '@laravel/stream-react';
import { ArrowUp, Info, Plus, Square } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

type Message = {
    id?: number;
    type: 'response' | 'error' | 'prompt';
    content: string;
};

type ChatType = {
    id: number;
    title: string;
    provider: string;
    model?: string;
    messages: Message[];
    created_at: string;
    updated_at: string;
};

type PageProps = {
    auth: {
        user?: {
            id: number;
            name: string;
            email: string;
        };
    };
    chat?: ChatType;
    availableModels: Record<string, { label: string; models: Record<string, string> }>;
    flash?: {
        stream?: boolean;
    };
};

function ChatWithStream({
    chat,
    auth,
    flash,
    availableModels,
}: {
    chat: ChatType | undefined;
    auth: PageProps['auth'];
    flash: PageProps['flash'];
    availableModels: Record<string, { label: string; models: Record<string, string> }>;
}) {
    const [messages, setMessages] = useState<Message[]>(chat?.messages || []);
    const [currentTitle, setCurrentTitle] = useState<string>(chat?.title || 'Untitled');
    const [shouldGenerateTitle, setShouldGenerateTitle] = useState<boolean>(false);
    const [isTitleStreaming, setIsTitleStreaming] = useState<boolean>(false);
    const [shouldUpdateSidebar, setShouldUpdateSidebar] = useState<boolean>(false);

    // Get initial model selection (format: "provider:model")
    const getInitialModel = () => {
        if (chat?.provider && chat?.model) {
            const fullModel = `${chat.provider}:${chat.model}`;
            console.log('Initial model from chat:', fullModel);
            console.log('Chat provider:', chat.provider, 'Chat model:', chat.model);
            return fullModel;
        }
        // Fallback to first provider and its first model
        const firstProvider = Object.keys(availableModels)[0];
        const firstModel = Object.keys(availableModels[firstProvider]?.models || {})[0];
        const fallbackModel = `${firstProvider}:${firstModel}`;
        console.log('Fallback model:', fallbackModel);
        return fallbackModel;
    };

    const [selectedModel, setSelectedModel] = useState<string>(getInitialModel());
    const [inputValue, setInputValue] = useState<string>('');
    const inputRef = useRef<HTMLTextAreaElement>(null);

    const currentChatId = chat?.id || null;
    const streamUrl = currentChatId ? `/chat/${currentChatId}/stream` : '/chat/stream';

    const { data, send, isStreaming, isFetching, cancel, id } = useStream(streamUrl, {
        onData: (chunk) => {
            console.log('[useStream] Received chunk:', chunk);
        },
        onResponse: (response) => {
            console.log('[useStream] Stream started:', response.status);
        },
        onFinish: () => {
            console.log('[useStream] Stream finished');
        },
    });

    // Auto-focus input and handle auto-streaming on mount
    useEffect(() => {
        inputRef.current?.focus();

        // Auto-stream if we have a chat with exactly 1 message (newly created chat)
        // OR if flash.stream is true (fallback)
        const shouldAutoStream = chat?.messages?.length === 1 || (flash?.stream && chat?.messages && chat.messages.length > 0);

        if (shouldAutoStream) {
            setTimeout(() => {
                send({ messages: chat.messages });
            }, 100);
        }
    }, [chat?.messages, flash?.stream, send]); // Only run on mount

    // Scroll to bottom when streaming
    useEffect(() => {
        if (isStreaming) {
            window.scrollTo(0, document.body.scrollHeight);
        }
    }, [isStreaming, data]);

    // Focus input when streaming completes and trigger title generation
    useEffect(() => {
        if (!isStreaming && inputRef.current) {
            inputRef.current.focus();

            // Trigger title generation if this is an authenticated user with "Untitled" chat and we have a response
            if (auth.user && chat && currentTitle === 'Untitled' && data && data.trim()) {
                setShouldGenerateTitle(true);
                setShouldUpdateSidebar(true);
            }
        }
    }, [isStreaming, auth.user, chat, currentTitle, data]);

    // Update current title when chat changes
    useEffect(() => {
        if (chat?.title) {
            setCurrentTitle(chat.title);
        }
    }, [chat?.title]);

    // Track title state changes
    useEffect(() => {
        // Title state tracking
    }, [currentTitle, isTitleStreaming]);

    const handleSubmit = useCallback(
        (e: FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            const query = inputValue.trim();

            if (!query) return;

            const toAdd: Message[] = [];

            // If there's a completed response from previous streaming, add it first
            if (data && data.trim()) {
                toAdd.push({
                    type: 'response',
                    content: data,
                });
            }

            // Add the new prompt
            toAdd.push({
                type: 'prompt',
                content: query,
            });

            // Update local state
            setMessages((prev) => [...prev, ...toAdd]);

            // Send all messages including the new ones with selected model
            send({
                messages: [...messages, ...toAdd],
                model: selectedModel,
            });

            setInputValue('');
            inputRef.current?.focus();
        },
        [send, data, messages, selectedModel, inputValue],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (inputValue.trim() && !isStreaming && !isFetching) {
                    handleSubmit(e as any);
                }
            }
        },
        [inputValue, isStreaming, isFetching, handleSubmit],
    );

    const handleStop = useCallback(() => {
        cancel();
    }, [cancel]);

    return (
        <>
            <Head title={currentTitle} />
            {/* Title generator with working EventStream */}
            {shouldGenerateTitle && auth.user && chat && (
                <TitleGenerator
                    chatId={chat.id}
                    onTitleUpdate={(newTitle, isStreaming = false) => {
                        setCurrentTitle(newTitle);
                        setIsTitleStreaming(isStreaming);
                        document.title = `${newTitle} - LaraChat`;
                    }}
                    onComplete={() => {
                        setIsTitleStreaming(false);
                        setShouldGenerateTitle(false);
                    }}
                />
            )}

            {/* Sidebar title updater - separate EventStream for sidebar */}
            {shouldUpdateSidebar && auth.user && chat && (
                <SidebarTitleUpdater
                    chatId={chat.id}
                    onComplete={() => {
                        setShouldUpdateSidebar(false);
                    }}
                />
            )}

            <AppLayout
                currentChatId={chat?.id}
                className="flex h-[calc(100vh-theme(spacing.4))] flex-col overflow-hidden md:h-[calc(100vh-theme(spacing.8))]"
            >
                {!auth.user && (
                    <div className="bg-background flex-shrink-0 border-b p-4">
                        <Alert className="mx-auto max-w-3xl">
                            <Info className="h-4 w-4" />
                            <AlertDescription>
                                You're chatting anonymously. Your conversation won't be saved.
                                <Button variant="link" className="h-auto p-0 text-sm" onClick={() => router.visit('/login')}>
                                    Sign in to save your chats
                                </Button>
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {/* Chat Title Display */}
                {auth.user && chat && (
                    <div className="bg-background flex-shrink-0 border-b px-4 py-3">
                        <div className="mx-auto max-w-3xl">
                            <h1 className="text-foreground text-lg font-semibold">
                                {currentTitle}
                                {isTitleStreaming && <span className="ml-1 animate-pulse">|</span>}
                            </h1>
                        </div>
                    </div>
                )}

                <Conversation messages={messages} streamingData={data} isStreaming={isStreaming} streamId={id} />

                <div className="bg-background flex-shrink-0 border-t">
                    <div className="mx-auto max-w-3xl p-4">
                        <form onSubmit={handleSubmit}>
                            <InputGroup>
                                <InputGroupTextarea
                                    ref={inputRef}
                                    placeholder="Ask, Search or Chat..."
                                    value={inputValue}
                                    onChange={(e) => setInputValue(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    disabled={isStreaming || isFetching}
                                />
                                <InputGroupAddon align="block-end">
                                    <InputGroupButton variant="outline" className="rounded-full" size="icon-xs">
                                        <Plus />
                                    </InputGroupButton>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <InputGroupButton variant="ghost" className="font-normal">
                                                {(() => {
                                                    if (!selectedModel || !selectedModel.includes(':')) {
                                                        return 'Select Model';
                                                    }

                                                    // Split only at first colon to handle model keys with colons (e.g., AWS Bedrock)
                                                    const colonIndex = selectedModel.indexOf(':');
                                                    const provider = selectedModel.substring(0, colonIndex);
                                                    const modelKey = selectedModel.substring(colonIndex + 1);

                                                    const providerData = availableModels[provider];

                                                    if (!providerData) {
                                                        console.log('Provider not found:', provider, 'Available:', Object.keys(availableModels));
                                                        return 'Select Model';
                                                    }

                                                    const modelLabel = providerData.models[modelKey];

                                                    if (!modelLabel) {
                                                        console.log('Model not found:', modelKey, 'Available:', Object.keys(providerData.models));
                                                        return providerData.label;
                                                    }

                                                    return `${providerData.label}: ${modelLabel}`;
                                                })()}
                                            </InputGroupButton>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent side="top" align="start" className="[--radius:0.95rem]">
                                            {Object.entries(availableModels).flatMap(([provider, { label, models }]) =>
                                                Object.entries(models).map(([modelKey, modelLabel]) => (
                                                    <DropdownMenuItem
                                                        key={`${provider}:${modelKey}`}
                                                        onClick={() => setSelectedModel(`${provider}:${modelKey}`)}
                                                    >
                                                        {modelLabel}
                                                    </DropdownMenuItem>
                                                )),
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <InputGroupText className="ml-auto">{inputValue.length} chars</InputGroupText>
                                    <Separator orientation="vertical" className="!h-4" />
                                    <InputGroupButton
                                        variant={isStreaming ? 'destructive' : 'default'}
                                        className="rounded-full"
                                        size="icon-xs"
                                        disabled={!isStreaming && (!inputValue.trim() || isFetching)}
                                        onClick={isStreaming ? handleStop : undefined}
                                        type={isStreaming ? 'button' : 'submit'}
                                    >
                                        {isStreaming ? <Square /> : <ArrowUp />}
                                        <span className="sr-only">{isStreaming ? 'Stop' : 'Send'}</span>
                                    </InputGroupButton>
                                </InputGroupAddon>
                            </InputGroup>
                        </form>
                    </div>
                </div>
            </AppLayout>
        </>
    );
}

export default function Chat() {
    const { auth, chat, flash, availableModels } = usePage<PageProps>().props;

    // Use the chat ID as a key to force complete re-creation of the ChatWithStream component
    // This ensures useStream is completely reinitialized with the correct URL
    const key = chat?.id ? `chat-${chat.id}` : 'no-chat';

    return <ChatWithStream key={key} chat={chat} auth={auth} flash={flash} availableModels={availableModels} />;
}
