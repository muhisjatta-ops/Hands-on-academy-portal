# School Portal — Phase 1 setup

Laravel 12 API + React 19 SPA (Vite, TypeScript) + PostgreSQL 16.

## 1. Backend

```bash
composer create-project laravel/laravel backend
cd backend

composer require laravel/sanctum spatie/laravel-permission \
    pragmarx/google2fa-laravel jenssegers/agent

php artisan install:api          # publishes Sanctum config + migration
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan session:table        # database sessions — needed for session management
```

### `.env`

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=school_portal
DB_USERNAME=school
DB_PASSWORD=secret

# Cookie auth: the session cookie must be readable by the SPA origin.
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false      # true in production, always
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
```

In production these become `SESSION_DOMAIN=.school.com`,
`SANCTUM_STATEFUL_DOMAINS=app.school.com`, and `SESSION_SECURE_COOKIE=true`.
The shared parent domain is not optional — without it the browser drops the
cookie and every request looks unauthenticated.

### `config/cors.php`

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL')],
'supports_credentials' => true,   // the whole thing fails silently without this
```

### `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();

    $middleware->alias([
        'two-factor' => App\Http\Middleware\RequireTwoFactor::class,
        'role'       => Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
    ]);
})
```

Then copy the files from `backend/` in this folder into place and run:

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan tinker
# >>> $u = User::create(['name'=>'Admin','email'=>'admin@school.test','password'=>'change-me-now']);
# >>> $u->assignRole('super-admin');
php artisan serve
```

## 2. Frontend

```bash
npm create vite@latest frontend -- --template react-ts
cd frontend
npm install react-router-dom
npm install -D tailwindcss @tailwindcss/vite
```

`.env.local`:

```dotenv
VITE_API_URL=http://localhost:8000
```

Wire it up in `src/main.tsx`:

```tsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './auth/AuthProvider';
import { RequireAuth } from './auth/RequireAuth';
import Login from './pages/Login';

<BrowserRouter>
  <AuthProvider>
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/" element={
        <RequireAuth><Dashboard /></RequireAuth>
      } />
      <Route path="/students" element={
        <RequireAuth permission="students.view"><Students /></RequireAuth>
      } />
    </Routes>
  </AuthProvider>
</BrowserRouter>
```

## 3. Verify before moving on

- [ ] Login with a non-2FA account, refresh the page, still signed in
- [ ] Wrong password five times → account locks, row in `security_events`
- [ ] Enrol in 2FA, sign out, sign in again → code is demanded
- [ ] A recovery code works once and then does not
- [ ] `GET /api/auth/sessions` lists your session; another browser adds a second
- [ ] A teacher account gets 403 on an admin-only route
- [ ] Password reset email arrives (use `MAIL_MAILER=log` locally) and kills sessions

The lockout and the "recovery code works once" cases are the two that break
quietly later. Test them now.
