# Codebase Structure

## Project Root
```
larachat/
├── app/                      # Laravel application code
├── bootstrap/                # Bootstrap files (app.php, providers.php)
├── config/                   # Configuration files
├── database/                 # Migrations, factories, seeders
├── public/                   # Public web root
├── resources/                # Frontend assets and views
├── routes/                   # Route definitions
├── storage/                  # App storage (logs, cache, uploads)
├── tests/                    # Pest/PHPUnit tests
├── vendor/                   # Composer dependencies
├── node_modules/             # npm dependencies
├── .env                      # Environment configuration
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
└── CLAUDE.md                 # Development guidance
```

## Backend Structure (`app/`)

### Controllers
```
app/Http/Controllers/
├── Controller.php                    # Base controller
├── ChatController.php                # Main chat logic
├── Auth/                             # Authentication controllers
│   ├── AuthenticatedSessionController.php
│   ├── RegisteredUserController.php
│   ├── PasswordResetLinkController.php
│   └── ...
├── Settings/                         # User settings
│   ├── ProfileController.php
│   └── PasswordController.php
└── Api/                              # API endpoints (if needed)
    └── ChatController.php
```

### Models
```
app/Models/
├── User.php                          # User model
├── Chat.php                          # Chat conversation model
└── Message.php                       # Chat message model
```

### Policies
```
app/Policies/
└── ChatPolicy.php                    # Authorization for chat operations
```

### Requests (Form Validation)
```
app/Http/Requests/
├── Auth/
│   └── LoginRequest.php
└── Settings/
    └── ProfileUpdateRequest.php
```

### Middleware
```
app/Http/Middleware/
├── HandleInertiaRequests.php         # Share global Inertia data
└── HandleAppearance.php              # Theme preferences
```

## Frontend Structure (`resources/js/`)

### Pages (Inertia Components)
```
resources/js/pages/
├── chat.tsx                          # Main chat page
├── dashboard.tsx                     # Dashboard
├── auth/                             # Auth pages
│   ├── login.tsx
│   ├── register.tsx
│   ├── forgot-password.tsx
│   ├── reset-password.tsx
│   ├── verify-email.tsx
│   └── confirm-password.tsx
└── settings/                         # Settings pages
    ├── profile.tsx
    ├── password.tsx
    └── appearance.tsx
```

### Components
```
resources/js/components/
├── ui/                               # shadcn/ui components
│   ├── button.tsx
│   ├── input.tsx
│   ├── dialog.tsx
│   ├── dropdown-menu.tsx
│   ├── avatar.tsx
│   ├── sidebar.tsx
│   └── ...
├── conversation.tsx                  # Chat message display
├── chat-list.tsx                     # Chat sidebar list
├── title-generator.tsx               # Auto-title generation
├── chat-title-updater.tsx            # Real-time title updates
├── sidebar-title-updater.tsx         # Sidebar title sync
├── app-header.tsx                    # Application header
├── app-sidebar.tsx                   # Application sidebar
├── app-shell.tsx                     # Layout shell
├── appearance-dropdown.tsx           # Theme selector
└── ...
```

### Layouts
```
resources/js/layouts/
├── app-layout.tsx                    # Main app layout
├── auth-layout.tsx                   # Auth layout wrapper
├── app/
│   ├── app-header-layout.tsx
│   └── app-sidebar-layout.tsx
├── auth/
│   ├── auth-card-layout.tsx
│   ├── auth-simple-layout.tsx
│   └── auth-split-layout.tsx
└── settings/
    └── layout.tsx
```

### Hooks
```
resources/js/hooks/
├── use-appearance.tsx                # Theme management
├── use-mobile.tsx                    # Mobile detection
├── use-mobile-navigation.ts          # Mobile nav state
└── use-initials.tsx                  # User initials helper
```

### Types
```
resources/js/types/
├── index.d.ts                        # Main type definitions
├── global.d.ts                       # Global types
└── vite-env.d.ts                     # Vite environment types
```

### Utilities
```
resources/js/lib/
└── utils.ts                          # Utility functions (cn, clsx)
```

## Routes (`routes/`)
```
routes/
├── web.php                           # Main web routes
├── auth.php                          # Authentication routes
└── settings.php                      # Settings routes
```

## Database (`database/`)
```
database/
├── migrations/                       # Database migrations
├── factories/                        # Model factories for testing
├── seeders/                          # Database seeders
└── database.sqlite                   # SQLite database file
```

## Tests (`tests/`)
```
tests/
├── Feature/                          # Feature tests (user-facing)
│   ├── Auth/                         # Auth feature tests
│   └── ...
├── Unit/                             # Unit tests (isolated logic)
└── Pest.php                          # Pest configuration
```

## Configuration Files

### Backend
- `bootstrap/app.php` - Application bootstrap, middleware, routing
- `bootstrap/providers.php` - Service provider registration
- `config/*.php` - Various configuration files

### Frontend
- `vite.config.ts` - Vite build configuration
- `tsconfig.json` - TypeScript configuration
- `eslint.config.js` - ESLint configuration
- `.prettierrc` - Prettier formatting rules
- `components.json` - shadcn/ui configuration
- `tailwind.config.js` - Tailwind CSS configuration (if exists)

## Key Patterns

### Inertia.js Flow
1. Route defined in `routes/web.php`
2. Controller returns `Inertia::render('PageName', $props)`
3. Page component in `resources/js/pages/` receives props
4. No separate API needed - server-driven UI

### Streaming Implementation
1. Backend: `response()->stream()` or `response()->eventStream()`
2. Frontend: `useStream` or `useEventStream` hooks
3. Real-time updates without polling

### Authentication
1. Laravel Breeze scaffolding
2. Session-based authentication
3. Middleware protection in `routes/web.php`
4. Auth pages in `resources/js/pages/auth/`

### Component Organization
- **UI Components**: Reusable design system components (buttons, inputs)
- **Feature Components**: Domain-specific components (conversation, chat-list)
- **Page Components**: Full page Inertia components
- **Layout Components**: Wrapping layouts with shared structure