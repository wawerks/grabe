<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        
    <script>
        window.lostItemsStore = "{{ route('lost-items.store') }}";
        window.foundItemsStore = "{{ route('found-items.store') }}";
        window.userID = "{{ route('user.id') }}";
        window.lostItemsUrl = "{{ route('lost-items.index') }}";
        window.foundItemsUrl = "{{ route('found-items.index') }}";
    </script>
    </body>
</html>
