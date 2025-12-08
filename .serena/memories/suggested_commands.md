# Suggested Commands Reference

## Development Server Commands

### Start Full Development Environment
```bash
composer dev
# Runs concurrently: server, queue worker, logs, and Vite
# Best for full-stack development
```

### Start Individual Services
```bash
# Terminal 1: Laravel development server
php artisan serve

# Terminal 2: Queue worker (for background jobs)
php artisan queue:listen --tries=1

# Terminal 3: Log viewer
php artisan pail --timeout=0

# Terminal 4: Vite dev server (frontend)
npm run dev
```

### SSR Development
```bash
composer dev:ssr
# Builds SSR bundle and starts server with SSR support
```

## Frontend Development

### Build Commands
```bash
npm run dev          # Start Vite dev server
npm run build        # Production build
npm run build:ssr    # Build with SSR support
```

### Code Quality
```bash
npm run format       # Format code with Prettier
npm run format:check # Check formatting without fixing
npm run lint         # Fix linting issues with ESLint
npm run types        # TypeScript type checking
```

## Backend Development

### Artisan Commands
```bash
php artisan serve              # Start development server
php artisan queue:listen       # Start queue worker
php artisan pail              # Real-time log viewer
php artisan inertia:start-ssr # Start SSR server
```

### Database
```bash
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Fresh migration (drops all tables)
php artisan db:seed          # Run seeders
```

### Code Generation
```bash
php artisan make:model ModelName -mfsc    # Model with migration, factory, seeder, controller
php artisan make:controller ControllerName
php artisan make:request RequestName
php artisan make:policy PolicyName
php artisan make:test TestName           # Feature test
php artisan make:test TestName --unit    # Unit test
```

## Testing

### Run Tests
```bash
composer test                            # Run all tests (config:clear + test)
php artisan test                         # Run all tests
php artisan test --filter=testName      # Run specific test
php artisan test tests/Feature/Auth     # Run tests in specific directory
php artisan test tests/Feature/ExampleTest.php  # Run specific file
```

### Testing with Coverage
```bash
php artisan test --coverage
```

## Code Quality & Formatting

### PHP Code Formatting
```bash
./vendor/bin/pint               # Format PHP code (Laravel Pint)
./vendor/bin/pint --dirty       # Format only changed files
```

### Frontend Formatting
```bash
npm run format       # Format with Prettier (auto-organize imports)
npm run lint         # Fix ESLint issues
npm run types        # TypeScript type check (no emit)
```

## Laravel Boost (MCP Server)
Laravel Boost MCP server provides powerful tools for this application:
- `list-artisan-commands` - Check available Artisan parameters
- `get-absolute-url` - Generate correct project URLs
- `tinker` - Execute PHP code for debugging
- `database-query` - Read from database
- `browser-logs` - Read browser logs and errors
- `search-docs` - Search version-specific Laravel documentation

## Git Workflow
```bash
git status              # Check current status
git branch              # List branches
git checkout -b feature/name  # Create feature branch
git diff                # Review changes before commit
```

## Environment Setup
```bash
composer install        # Install PHP dependencies
npm install            # Install Node dependencies
cp .env.example .env   # Copy environment file
php artisan key:generate  # Generate application key
touch database/database.sqlite  # Create SQLite database (if needed)
```

## Troubleshooting Commands
```bash
# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Node/npm issues
npm cache clean --force
rm -rf node_modules && npm install

# Vite manifest error
npm run build
```

## macOS (Darwin) System Commands
```bash
ls -la              # List files with details
grep -r "pattern"   # Search in files recursively
find . -name "*.php"  # Find files by pattern
cd /path/to/dir     # Change directory
cat file.txt        # Display file contents
```