<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1? shrink-to-fit=no">
        <meta name="csrf-token" value="{{ csrf_token() }}"/>
        <meta http-equiv="X-UA-Compatible" content="ie-edge">        
        @vite(['resources/js/app.js'])
       
        {{-- <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script src="{{ asset('js/app.js') }}" defer></script> --}}
        
        <title>{{ env('APP_NAME') }}</title>

    </head>
    <body>
        <div id="app">
        </div>        
        {{-- <script src="{{ mix('js/app.js') }}"></script>                              --}}
    </body>
</html>
