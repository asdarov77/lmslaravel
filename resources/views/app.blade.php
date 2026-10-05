<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1? shrink-to-fit=no">
        <meta name="csrf-token" value="{{ csrf_token() }}"/>
        <meta http-equiv="X-UA-Compatible" content="ie-edge">

        {{-- Идентификация: знак, описание и цвет панели браузера.
             Цвет объявлен двумя media-тегами, чтобы адресная строка и
             панель мобильного браузера совпадали с выбранной темой. --}}
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
        <meta name="description" content="{{ __('app.description') }}">
        <meta name="theme-color" content="#1a5fb4" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#14171a" media="(prefers-color-scheme: dark)">

        {{--
            Тема применяется до загрузки стилей и приложения: иначе
            страница сначала отрисовалась бы светлой и мигнула, что
            особенно заметно ночью. Значение выбирается из
            localStorage, при его отсутствии — из системной настройки.
            Ключ и логика — те же, что в resources/js/utils/theme.js.
        --}}
        <script>
            (function () {
                var key = 'ui-theme';
                var mode = 'auto';
                try { mode = localStorage.getItem(key) || 'auto'; } catch (e) {}

                var dark = mode === 'dark'
                    || (mode === 'auto' && window.matchMedia
                        && window.matchMedia('(prefers-color-scheme: dark)').matches);

                var root = document.documentElement;
                root.setAttribute('data-theme', dark ? 'dark' : 'light');
                if (dark) root.classList.add('v-theme--dark');
            })();
        </script>

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
