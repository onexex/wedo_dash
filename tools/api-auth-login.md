# WeDo sign-in API — integration guide

Lets another app (e.g. a Laravel + Vue site) sign its users in with their **WeDo
username and password**. Login only: WeDo answers "yes, this is an active WeDo
user" plus a basic profile and a short-lived signed token. No other WeDo data.

Code: `api/auth-login.php`, `includes/api-auth-lib.php`. Tests: `tests/ApiAuthLoginTest.php`.

| Server     | URL                                                   |
|------------|-------------------------------------------------------|
| Production | `https://dashboard.wedoinc.ph/api/auth-login.php`     |
| Staging    | `https://dashboard.wedoinc.xyz/api/auth-login.php`    |
| Local      | `http://localhost/wedo/api/auth-login.php`            |

**Server to server only.** The other app's *server* (Laravel) calls WeDo. The
API key must never reach the browser / Vue code, so Vue posts the login form to
Laravel, and Laravel calls WeDo. There are no CORS headers, so browsers can't
call it directly.

---

## 1. Set up a key (once per WeDo server)

1. Run the migration `sql/2026-10-20-add-api-auth.sql` on that server's
   database (phpMyAdmin). `sql/check-migrations.sql` shows it as OK afterwards.
   Until it runs, the API answers `503 not_ready`.
2. Make a random key (at least 32 characters):
   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```
3. Add it to that server's `config.local.php` (never committed). The name
   (`timekeeper` here) identifies the app in the activity log and in the token:
   ```php
   'api_clients' => [
       'timekeeper' => ['key' => '<the 64-character key>'],
       // optional: 'per_minute' => 60 (requests a minute), 'token_ttl' => 3600 (seconds)
   ],
   ```
   Or set the env var `WEDO_API_CLIENTS="timekeeper:<key>"` (several: comma-separated).
4. Put the **same key** in the other app's `.env`, e.g.
   ```
   WEDO_API_URL=https://dashboard.wedoinc.ph/api/auth-login.php
   WEDO_API_KEY=<the 64-character key>
   ```
   Use a different key for staging and production. To revoke an app, remove its
   line from `config.local.php`.

---

## 2. Request

`POST`, HTTPS only, JSON (or a normal form post).

```http
POST /api/auth-login.php HTTP/1.1
Host: dashboard.wedoinc.ph
Content-Type: application/json
X-WeDo-Key: <the key>

{"username": "jdelacruz", "password": "their password", "ip": "203.0.113.25"}
```

- `X-WeDo-Key` can also be sent as `Authorization: Bearer <key>`.
- `ip` (optional but recommended): the **end user's** address as Laravel sees it
  (`$request->ip()`). WeDo's "50 failed sign-ins from one address" lock then
  applies per person instead of to your whole server.

## 3. Responses

Success, `200`:
```json
{
  "ok": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ3ZWRvIiwiYXVkIjoidGltZWtlZXBlciIsInN1YiI6IlRDLTAwMDEi…",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": "2026-10-20T09:15:00+00:00",
  "user": {
    "emp_id": "WD-0123",
    "username": "jdelacruz",
    "first_name": "Juan",
    "middle_name": "Santos",
    "last_name": "Dela Cruz",
    "name": "Juan Dela Cruz Jr",
    "company_id": "WD",
    "company": "WeDo BPO Inc.",
    "role_id": 3,
    "role": "employee"
  }
}
```
`role`: `admin` (WeDo user type 1, sees the whole organization), `team_lead`
(2, sees their team), `employee` (anything else). Use `emp_id` as the stable
link between your user record and WeDo.

Failures always look like `{"ok": false, "error": "<code>", "message": "<text you can show>"}`:

| HTTP | `error`                    | Meaning / what to do |
|------|----------------------------|----------------------|
| 401  | `invalid_credentials`      | Wrong username or password (deliberately the same for an unknown username). Counts toward WeDo's lockout. |
| 429  | `locked`                   | 5 wrong passwords for this username, or 50 failed sign-ins from this address, in 15 minutes. `retry_after` = seconds. Shared with the WeDo website. HR can unlock it from the 201 profile. |
| 403  | `account_inactive`         | Resigned / terminated / AWOL in WeDo. |
| 403  | `password_change_required` | Still on a default or temporary password. Tell them to sign in to WeDo, set their own password, then come back. |
| 422  | `missing_fields`           | No username or password sent. |
| 401  | `invalid_api_key`          | Missing or wrong `X-WeDo-Key` (your config problem, not the user's). |
| 429  | `rate_limited`             | Over this key's requests a minute (default 60), or 20 wrong keys from your server in 15 minutes. Wait `retry_after` seconds. |
| 403  | `https_required`           | Called over plain http. |
| 405  | `method_not_allowed`       | Not a POST. |
| 503  | `not_ready`                | The migration hasn't run on that WeDo server. |
| 500  | `server_error`             | Something failed on WeDo's side; try again. |

`429` responses also carry a `Retry-After` header.

## 4. The token

A standard JWT, **HS256, signed with your own API key**, so your app can check it
itself (no call back to WeDo). Claims: `iss` = `"wedo"`, `aud` = your app's name,
`sub` = EmpID, `name`, `iat`, `exp`, `jti`. Your app doesn't need it if it just
starts its own Laravel session after a `200`. It's there if you want to pass a
proof of the sign-in between your own services. WeDo does not accept it back:
there is no "refresh" or "who am I" call.

## 5. Laravel example

```php
// app/Http/Controllers/Auth/WeDoLoginController.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Models\User;

public function store(Request $request)
{
    $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);

    $res = Http::timeout(15)
        ->withHeaders(['X-WeDo-Key' => config('services.wedo.key')])
        ->acceptJson()
        ->post(config('services.wedo.url'), $data + ['ip' => $request->ip()]);

    if (!$res->ok() || !$res->json('ok')) {
        // show WeDo's message: "Wrong username or password.", "Try again in 12 minutes.", …
        throw ValidationException::withMessages(['username' => $res->json('message') ?? 'Sign-in failed.']);
    }

    $u = $res->json('user');
    $user = User::updateOrCreate(['wedo_emp_id' => $u['emp_id']], [
        'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role'], 'company' => $u['company'],
    ]);
    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->intended('/');
}
```

```php
// config/services.php
'wedo' => ['url' => env('WEDO_API_URL'), 'key' => env('WEDO_API_KEY')],
```

Checking the token yourself (optional), with `firebase/php-jwt`:
```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$claims = JWT::decode($token, new Key(config('services.wedo.key'), 'HS256'));
// $claims->sub = EmpID, $claims->aud = your app's name, expiry already checked
```

## 6. What WeDo records

- Every request: `api_auth_requests` (app name, end user's address, typed
  username, result; never the password). Kept 30 days.
- Every attempt also counts in `login_attempts`, together with the website.
- Each successful sign-in: the activity log (`dars`), "Signed in to <app> (sign-in API)".
