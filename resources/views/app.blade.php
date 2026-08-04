<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B0C0E">

        <title inertia>{{ config('app.name', 'Barbearia Oliveira Alves') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:500,600,700|ibm-plex-sans:400,500,600|ibm-plex-mono:400,500" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="bg-background text-foreground font-sans antialiased">
        @inertia
    </body>
</html>
