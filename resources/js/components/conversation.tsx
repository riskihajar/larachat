import { cn } from '@/lib/utils';
import { Brain, ChevronDown, ChevronRight, Wrench } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

type Message = {
    id?: number;
    type: 'response' | 'error' | 'prompt';
    content: string;
    saved?: boolean;
    // Enhanced fields for thinking and tool use display
    reasoning?: string;
    tool_calls?: Array<{
        name: string;
        arguments: Record<string, unknown>;
        result?: unknown;
    }>;
};

interface ConversationProps {
    messages: Message[];
    streamingData?: string;
    isStreaming: boolean;
    streamId?: string;
}

// Component untuk menampilkan reasoning/thinking process
function ReasoningDisplay({ reasoning }: { reasoning?: string }) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (!reasoning) return null;

    return (
        <div className="my-2 rounded-md border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/50">
            <button
                type="button"
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-amber-800 dark:text-amber-200"
            >
                <Brain className="h-4 w-4" />
                <span>Thinking</span>
                {isExpanded ? <ChevronDown className="ml-auto h-4 w-4" /> : <ChevronRight className="ml-auto h-4 w-4" />}
            </button>
            {isExpanded && (
                <div className="border-t border-amber-200 px-3 py-2 dark:border-amber-800">
                    <div className="prose prose-sm prose-amber dark:prose-invert max-w-none">
                        <ReactMarkdown>{reasoning}</ReactMarkdown>
                    </div>
                </div>
            )}
        </div>
    );
}

// Component untuk menampilkan tool calls
function ToolCallsDisplay({ tool_calls }: { tool_calls?: Message['tool_calls'] }) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (!tool_calls || tool_calls.length === 0) return null;

    return (
        <div className="my-2 rounded-md border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/50">
            <button
                type="button"
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-blue-800 dark:text-blue-200"
            >
                <Wrench className="h-4 w-4" />
                <span>Tool Use ({tool_calls.length})</span>
                {isExpanded ? <ChevronDown className="ml-auto h-4 w-4" /> : <ChevronRight className="ml-auto h-4 w-4" />}
            </button>
            {isExpanded && (
                <div className="space-y-2 border-t border-blue-200 px-3 py-2 dark:border-blue-800">
                    {tool_calls.map((tool, idx) => (
                        <div key={`${tool.name}-${idx}`} className="rounded bg-blue-100/50 p-2 dark:bg-blue-900/50">
                            <div className="flex items-center gap-2 font-mono text-xs text-blue-700 dark:text-blue-300">
                                <span className="font-semibold">{tool.name}</span>
                            </div>
                            {tool.arguments && (
                                <pre className="mt-1 overflow-x-auto rounded bg-blue-200/50 p-2 text-xs dark:bg-blue-800/50">
                                    {JSON.stringify(tool.arguments, null, 2)}
                                </pre>
                            )}
                            {tool.result != null && (
                                <div className="mt-2 border-t border-blue-200 pt-2 dark:border-blue-700">
                                    <div className="text-xs font-medium text-blue-600 dark:text-blue-400">Result:</div>
                                    <pre className="mt-1 overflow-x-auto rounded bg-green-100/50 p-2 text-xs dark:bg-green-900/50">
                                        {JSON.stringify(tool.result, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Conversation({ messages, streamingData, isStreaming, streamId }: ConversationProps) {
    const scrollRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom when messages change or during streaming
    const [shouldAutoScroll, setShouldAutoScroll] = useState(true);

    const checkIfAtBottom = useCallback(() => {
        const container = scrollRef.current;
        if (!container) return false;

        const offset = Math.abs(container.scrollHeight - container.scrollTop - container.clientHeight);
        return offset <= 1; // Allow a small margin of error
    }, []);

    const handleScroll = useCallback(() => {
        const nearBottom = checkIfAtBottom();
        setShouldAutoScroll(nearBottom);
    }, [checkIfAtBottom]);

    useEffect(() => {
        if (scrollRef.current && shouldAutoScroll) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages.length, streamingData, shouldAutoScroll]);

    useEffect(() => {
        const container = scrollRef.current;
        if (container) {
            container.addEventListener('scroll', handleScroll);
            return () => container.removeEventListener('scroll', handleScroll);
        }
    }, [handleScroll]);

    return (
        <div ref={scrollRef} className="flex-1 overflow-x-hidden overflow-y-auto">
            <div className="mx-auto max-w-3xl space-y-4 p-4">
                {messages.length === 0 && <p className="text-muted-foreground mt-8 text-center">Type your message below and hit enter to send.</p>}
                {messages.map((message, index) => {
                    // Create a unique key that won't conflict between saved and new messages
                    const key = message.id ? `db-${message.id}` : `local-${index}-${message.content.substring(0, 10)}`;

                    return (
                        <div key={key} className={cn('relative', message.type === 'prompt' && 'flex justify-end')}>
                            <div
                                className={cn(
                                    'inline-block max-w-[80%] rounded-lg p-3',
                                    message.type === 'prompt' ? 'bg-primary text-primary-foreground' : 'bg-muted',
                                )}
                            >
                                <ReasoningDisplay reasoning={message.reasoning} />
                                <ToolCallsDisplay tool_calls={message.tool_calls} />
                                <div className={cn('prose prose-sm max-w-none', message.type === 'prompt' ? 'prose-invert' : 'dark:prose-invert')}>
                                    <ReactMarkdown remarkPlugins={[remarkGfm]}>{message.content}</ReactMarkdown>
                                </div>
                            </div>
                        </div>
                    );
                })}
                {streamingData && (
                    <div className="relative">
                        <div className="bg-muted inline-block max-w-[80%] rounded-lg p-3">
                            <div className="prose prose-sm dark:prose-invert max-w-none">
                                <ReactMarkdown remarkPlugins={[remarkGfm]}>{streamingData}</ReactMarkdown>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
