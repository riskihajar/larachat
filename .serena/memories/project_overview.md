# Laravel Chat Demo - Project Overview

## Project Purpose
A real-time AI chat application demonstrating Laravel's streaming capabilities with React. Features a ChatGPT-like interface with:
- Real-time streaming responses using Server-Sent Events (SSE)
- Message persistence for authenticated users
- Automatic chat title generation
- Beautiful UI with dark/light mode support
- Mobile-responsive design

## Tech Stack

### Backend
- **PHP**: 8.4.15
- **Laravel**: 12.16.0 (latest framework)
- **Database**: SQLite (default, configurable)
- **AI Integration**: OpenAI PHP client (openai-php/laravel ^0.13.0)
- **Streaming**: Laravel Stream package for SSE

### Frontend
- **React**: 19.0.0
- **TypeScript**: 5.7.2
- **Inertia.js**: 2.0.4 (server-driven UI)
- **Build Tool**: Vite 6.0
- **Styling**: Tailwind CSS 4.0.10
- **UI Components**: shadcn/ui (Radix UI primitives)
- **Icons**: lucide-react
- **Streaming Hooks**: @laravel/stream-react

### Development Tools
- **Testing**: Pest 3.8.2, PHPUnit 11.5.15
- **Code Quality**: Laravel Pint 1.22.1, ESLint 9.21.0, Prettier 3.5.3
- **Laravel Boost**: 1.1 (MCP server for Laravel development)
- **Routing**: Ziggy 2.5.3 (Laravel routes in JavaScript)

## Key Features
- Inertia.js for seamless Laravel-React integration (no separate API)
- Server-side routing with React components
- Streaming responses using useStream and useEventStream hooks
- Authentication with Laravel Breeze
- Real-time title generation via EventStream
- Chat persistence for logged-in users
- Theme management (dark/light/system)
- Mobile-first responsive design