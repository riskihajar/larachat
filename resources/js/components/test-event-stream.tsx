import { useEventStream } from '@laravel/stream-react';
import { useEffect, useState } from 'react';

interface TestEventStreamProps {
    chatId: number;
}

// DEBUG: Test component for EventStream - remove in production
export default function TestEventStream({ chatId }: TestEventStreamProps) {
    const [manualMessage, setManualMessage] = useState<string>('');
    const [status, setStatus] = useState<string>('Not started');

    const { message } = useEventStream(`/chat/${chatId}/title-stream`, {
        eventName: 'title-update',
        endSignal: '</stream>',
        onMessage: (event) => {
            console.log('useEventStream onMessage:', event);
            console.log('useEventStream data:', event.data);
        },
        onComplete: () => {
            console.log('useEventStream complete');
        },
        onError: (error) => {
            console.log('useEventStream error:', error);
        },
    });

    // Test with manual EventSource
    useEffect(() => {
        console.log('Starting manual EventSource test');
        setStatus('Connecting...');

        const eventSource = new EventSource(`/chat/${chatId}/title-stream`);

        eventSource.onopen = () => {
            console.log('Manual EventSource opened');
            setStatus('Connected');
        };

        eventSource.addEventListener('title-update', (event) => {
            console.log('Manual EventSource received title-update:', event.data);
            if (event.data !== '</stream>') {
                setManualMessage(event.data);
            }
        });

        eventSource.onmessage = (event) => {
            console.log('Manual EventSource onmessage:', event.data);
            if (event.data !== '</stream>') {
                setManualMessage(event.data);
            }
        };

        eventSource.onerror = (error) => {
            console.log('Manual EventSource error:', error);
            setStatus('Error: ' + error.type);
        };

        return () => {
            console.log('Closing manual EventSource');
            eventSource.close();
        };
    }, [chatId]);

    console.log('TestEventStream - useEventStream message:', message);

    return (
        <div className="border border-yellow-300 bg-yellow-100 p-4">
            <h3>Test EventStream</h3>
            <p>Chat ID: {chatId}</p>
            <p>useEventStream Message: {message || 'No message yet'}</p>
            <p>Manual EventSource Status: {status}</p>
            <p>Manual EventSource Message: {manualMessage || 'No message yet'}</p>
        </div>
    );
}
