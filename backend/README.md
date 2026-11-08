<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## UMTC monitoring setup

The React application uses the Laravel API for sign-in, workbook imports, portfolio monitoring, agent scope, and fieldwork schedules.

1. Enable PHP's `zip` and `gd` extensions in the `php.ini` used by both the web server and CLI. For workbook uploads, set `upload_max_filesize=20M` and `post_max_size=24M`.
2. From this directory, install backend dependencies and apply the forward-only migrations:

   ```sh
   composer install
   php artisan migrate
   ```

3. Provision the first administrator interactively. The password is prompted securely and must be at least 12 characters:

   ```sh
   php artisan app:create-admin
   ```

4. Start Laravel:

   ```sh
   php artisan serve --host=127.0.0.1 --port=8000
   ```

5. In another terminal, start the existing frontend:

   ```sh
   cd ../frontend
   npm install
   npm run dev
   ```

Sign in at the Vite URL with the administrator account. Use **Excel Import** to upload the complete `.xlsx` workbook. Review the preview before confirming: identical repeat rows are counted and conflicting account-number groups are skipped. The Data Input Agent is used for the portfolio assignment; the cluster reference sheets resolve the address to a unique location and province when possible. A mismatch between the Data Input Agent and the location reference is flagged for review without replacing the Data Input assignment. When no unique address match exists, the imported agent and cluster are retained while location details remain unverified. The authenticated `GET /api/locations/resolve?address=...` endpoint uses the same locally stored cluster reference data; full account addresses are not sent to an external geocoding provider. Create Senior Agent users and manage their Field Agent scope in **User Management**.

All monitoring pages read current portfolio records from the same database. Older records are retained when a new workbook is imported; records absent from the latest snapshot are archived as non-current. Import uploads are stored privately and pending previews expire after 24 hours.

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
