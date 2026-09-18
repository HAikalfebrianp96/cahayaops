<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sesi milik akun yang sudah dinonaktifkan harus langsung berhenti
        // berlaku. Ditempel di grup 'web' supaya SATU titik ini melindungi
        // seluruh halaman sekaligus — bukan dicek satu per satu di puluhan
        // controller. Ditaruh di akhir grup, jadi session & auth sudah siap
        // saat middleware ini dijalankan. Untuk tamu, middleware ini tidak
        // melakukan apa pun.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
