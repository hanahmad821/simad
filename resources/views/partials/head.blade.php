<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'MADIKA') : config('app.name', 'MADIKA') }}
</title>

@include('partials.madika-head')

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
