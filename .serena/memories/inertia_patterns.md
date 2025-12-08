# Inertia.js Patterns

## Core Concept
Inertia.js bridges Laravel backend with React frontend without building a separate API. Server-side routing drives the entire application.

## Version
Inertia.js v2.0 (both Laravel and React packages)

## Request Flow
```
User Action → Route → Controller → Inertia::render() → React Page Component
```

## Basic Pattern

### Backend (Controller)
```php
use Inertia\Inertia;

public function show(Chat $chat)
{
    $this->authorize('view', $chat);
    
    return Inertia::render('chat', [
        'chat' => $chat->load('messages'),
        'user' => auth()->user(),
    ]);
}
```

### Frontend (Page Component)
```typescript
import { PageProps } from '@/types';

interface ChatPageProps extends PageProps {
    chat: Chat;
}

export default function ChatPage({ chat, auth }: ChatPageProps) {
    // Component implementation
}
```

## Form Handling with useForm

### Basic Form
```typescript
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/login');
}

return (
    <form onSubmit={submit}>
        <input
            type="email"
            value={data.email}
            onChange={e => setData('email', e.target.value)}
        />
        {errors.email && <div>{errors.email}</div>}
        
        <button type="submit" disabled={processing}>
            Login
        </button>
    </form>
);
```

### Form with Callbacks
```typescript
post('/chats', {
    onSuccess: () => {
        // Clear form, show success message
    },
    onError: (errors) => {
        // Handle validation errors
    },
    preserveScroll: true,
});
```

## Navigation

### Using Link Component
```typescript
import { Link } from '@inertiajs/react';

<Link href="/dashboard" className="nav-link">
    Dashboard
</Link>

// With method override
<Link href="/logout" method="post" as="button">
    Logout
</Link>

// Preserve scroll position
<Link href="/page" preserveScroll>
    Next Page
</Link>
```

### Using router
```typescript
import { router } from '@inertiajs/react';

// Visit page
router.visit('/dashboard');

// POST request
router.post('/chats', { message: 'Hello' });

// DELETE request
router.delete(`/chats/${chatId}`);

// With options
router.visit('/page', {
    preserveScroll: true,
    preserveState: true,
    only: ['messages'], // Only reload specific props
});
```

## Shared Data (Global Props)

### Backend (HandleInertiaRequests Middleware)
```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'message' => session('message'),
                'error' => session('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
```

### Frontend (Access in Any Component)
```typescript
import { usePage } from '@inertiajs/react';

const { auth, flash } = usePage().props;
```

## Partial Reloads
Only reload specific props instead of entire page:

```typescript
router.reload({
    only: ['messages'], // Only reload 'messages' prop
    preserveScroll: true,
});
```

## Lazy Data Loading
Load expensive data only when needed:

### Backend
```php
return Inertia::render('chat', [
    'chat' => $chat,
    'messages' => Inertia::lazy(fn () => $chat->messages),
]);
```

### Frontend
```typescript
// Messages won't load until explicitly requested
router.reload({ only: ['messages'] });
```

## Inertia v2 New Features

### Polling
```typescript
import { router } from '@inertiajs/react';

// Poll every 5 seconds
router.reload({ 
    only: ['messages'],
    interval: 5000 
});
```

### Prefetching
```typescript
<Link href="/dashboard" prefetch>
    Dashboard
</Link>

// Or programmatically
router.prefetch('/dashboard');
```

### Deferred Props
```php
return Inertia::render('page', [
    'immediate' => $data,
    'deferred' => Inertia::defer(fn () => $expensiveData),
]);
```

```typescript
// Show skeleton while deferred props load
const { deferred } = usePage().props;

{deferred ? <Data /> : <Skeleton />}
```

### Infinite Scrolling (WhenVisible + Merging)
```php
return Inertia::render('page', [
    'items' => Inertia::merge(
        fn () => Item::paginate(20)
    ),
]);
```

```typescript
import { router } from '@inertiajs/react';

const loadMore = () => {
    router.reload({
        only: ['items'],
        preserveScroll: true,
    });
};
```

## File Uploads
```typescript
const { data, setData, post } = useForm({
    avatar: null as File | null,
});

<input
    type="file"
    onChange={e => setData('avatar', e.target.files?.[0] ?? null)}
/>

post('/profile/avatar', {
    forceFormData: true,
});
```

## Error Handling
```typescript
const { errors } = useForm();

// Display validation errors
{errors.email && <div className="error">{errors.email}</div>}

// Global error handling
import { router } from '@inertiajs/react';

router.on('error', (event) => {
    // Handle errors globally
});
```

## Layout Persistence
```typescript
import AppLayout from '@/layouts/app-layout';

// Set persistent layout
ChatPage.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);

export default ChatPage;
```

## Route Helpers (Ziggy)
```typescript
import { route } from 'ziggy-js';

// Generate Laravel route URLs in React
<Link href={route('chats.show', chat.id)}>
    View Chat
</Link>

// With query parameters
const url = route('search', { q: 'query', page: 2 });
```

## Type Safety
```typescript
// types/index.d.ts
export interface PageProps {
    auth: {
        user: User | null;
    };
    flash: {
        message?: string;
        error?: string;
    };
    ziggy: {
        location: string;
    };
}

// Page component
interface ChatPageProps extends PageProps {
    chat: Chat;
    messages: Message[];
}
```

## Best Practices

1. **Always use useForm**: Don't use native React state for forms
2. **Type your props**: Extend PageProps for type safety
3. **Preserve scroll**: Use `preserveScroll: true` for better UX
4. **Partial reloads**: Use `only` to reload specific props
5. **Shared data**: Put global data in HandleInertiaRequests middleware
6. **Named routes**: Use Ziggy for route generation
7. **Error display**: Always show validation errors near inputs
8. **Loading states**: Use `processing` from useForm
9. **CSRF automatic**: Inertia handles CSRF automatically for forms
10. **Lazy loading**: Use Inertia::lazy() for expensive data