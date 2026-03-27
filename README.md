<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).




<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AnonymousToken;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = JWTAuth::user();

        // Generate Anonymous Token
        $plainAnonymousToken = Str::random(64); // secure random token
        $hashedToken = hash('sha256', $plainAnonymousToken);

        $anonymousToken = AnonymousToken::create([
            'token_hash' => $hashedToken,
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'expires_at' => now()->addMinutes(30), // token lifetime
        ]);

        return response()->json([
            'jwt' => $token,
            'anonymous_token' => $plainAnonymousToken,
            'user' => [
                'name' => $user->name,
                'role' => $user->role->name,
                'department_id' => $user->department_id,
            ]
        ]);
    }

    public function verifyAnonymousToken(Request $request)
    {
        $plainToken = $request->input('anonymous_token');
        $hashed = hash('sha256', $plainToken);

        $token = AnonymousToken::where('token_hash', $hashed)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            return response()->json(['valid' => false], 401);
        }

        // Mark as used (one-time use)
        $token->update(['used_at' => now()]);

        return response()->json([
            'valid' => true,
            'role' => $token->role->name,
            'role_id' => $token->role_id,
            'department_id' => $token->department_id,
        ]);
    }

    public function importUsers(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {

            \App\Models\User::create([
                'name' => $row[0],
                'email' => $row[1],
                'password' => bcrypt($row[2]),
                'role_id' => $row[3],
                'department_id' => $row[4],
            ]);
        }

        fclose($handle);

        return response()->json(['message' => 'Users imported successfully']);
    }
}






<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Call AUTH SERVICE
        $response = Http::post(
            config('services.backend.url') . '/api/login',
            [
                'email' => $request->email,
                'password' => $request->password,
            ]
        );

        if (! $response->successful()) {
            return back()->withErrors([
                'email' => 'Invalid credentials',
            ]);
        }

        $data = $response->json();

        // STORE EVERYTHING IN SESSION
        session([
            'jwt_token'        => $data['jwt'],
            'anonymous_token'  => $data['anonymous_token'],
            'user_role'        => $data['user']['role'],
            'user_department'  => $data['user']['department_id'],
            'user'             => $data['user'],
        ]);

        // REDIRECT BASED ON ROLE
        return match ($data['user']['role']) {
            'admin'             => redirect()->route('admin.dashboard'),
            'student'           => redirect()->route('student.dashboard'),
            'lecturer'           => redirect()->route('lecture.dashboard'),
            'hod'               => redirect()->route('hod.dashboard'),
            'dean_of_students'  => redirect()->route('dean.dashboard'),
            'rector'            => redirect()->route('rector.dashboard'),
            default             => redirect('/'),
        };
    }

    public function destroy(Request $request)
    {
        session()->flush();
        return redirect('/login');
    }
}