# Code Style & Conventions

## PHP Conventions

### File Organization
- Controllers: `app/Http/Controllers/`
- Models: `app/Models/`
- Policies: `app/Policies/`
- Requests: `app/Http/Requests/`
- Middleware: `app/Http/Middleware/`

### Naming Conventions
- Classes: PascalCase (e.g., `ChatController`, `User`)
- Methods: camelCase (e.g., `createChat`, `streamResponse`)
- Properties: camelCase with explicit type hints
- Use descriptive names for variables and methods

### Code Style
- **Constructor Property Promotion**: Use PHP 8+ constructor property promotion
- **Type Declarations**: Always use explicit return type declarations
- **Curly Braces**: Always use for control structures, even for single-line
- **No Empty Constructors**: Don't allow empty `__construct()` with zero parameters

### Laravel Patterns
- Use Eloquent relationships with return type hints
- Prefer `Model::query()` over `DB::`
- Use Form Request classes for validation (not inline validation)
- Follow Laravel 12 streamlined structure (no Kernel.php files)
- Middleware and routing configured in `bootstrap/app.php`
- Service providers in `bootstrap/providers.php`

### Models
- Define casts in `casts()` method (not `$casts` property)
- Use factories for all models (in `database/factories/`)

## TypeScript/React Conventions

### File Organization
- Pages: `resources/js/pages/` (Inertia components)
- Components: `resources/js/components/`
- UI Components: `resources/js/components/ui/` (shadcn/ui)
- Layouts: `resources/js/layouts/`
- Hooks: `resources/js/hooks/`
- Types: `resources/js/types/`
- Utils: `resources/js/lib/`

### Naming Conventions
- Components: PascalCase (e.g., `ChatList.tsx`, `AppHeader.tsx`)
- Files: kebab-case (e.g., `chat-list.tsx`, `app-header.tsx`)
- Hooks: camelCase with `use` prefix (e.g., `useAppearance`, `useMobile`)
- Types/Interfaces: PascalCase (e.g., `User`, `ChatMessage`)

### TypeScript Style
- **Strict Mode**: TypeScript strict mode enabled
- **Explicit Types**: Always use proper type annotations
- **Path Aliases**: Use `@/` for `resources/js/` imports
- **No Implicit Any**: All types must be explicitly defined

### React Patterns
- Functional components with hooks (no class components)
- Use Inertia's `useForm` for forms (not native React state)
- Props should be properly typed with TypeScript interfaces
- Use React 19 features (latest version)

## Prettier Configuration
```json
{
  "semi": true,
  "singleQuote": true,
  "printWidth": 150,
  "tabWidth": 4,
  "plugins": ["prettier-plugin-organize-imports", "prettier-plugin-tailwindcss"]
}
```

## ESLint Configuration
- Extends: `@eslint/js`, `typescript-eslint`, `eslint-config-prettier`
- Plugins: `react`, `react-hooks`
- React 17+ JSX runtime (no React import needed)
- Rules: Hooks rules enforced, prop-types disabled

## Tailwind CSS Guidelines
- Use Tailwind 4.0 (not deprecated v3 utilities)
- No opacity utilities like `bg-opacity-*` (use `bg-black/*` instead)
- Use `shrink-*` not `flex-shrink-*`
- Use `text-ellipsis` not `overflow-ellipsis`
- Spacing: Use gap utilities for list spacing (not margins)
- Dark mode: Support via `dark:` prefix
- Custom functions: `clsx`, `cn` for conditional classes

## Inertia.js Patterns
- Use `Inertia::render()` for server responses
- Use `useForm` for form handling (CSRF automatic)
- Use `Link` component for navigation
- Props passed from controllers to React components
- No separate API layer needed