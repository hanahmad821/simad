@props(['letter'])
@php($people = app(\App\Services\AssignmentLetterService::class)->viewData($letter)['recipients'])
<ol class="mt-2 space-y-3">
    @foreach ($people as $index => $person)
        <li class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <p class="font-medium">{{ $index + 1 }}. {{ $person['nama'] }}</p>
            <p class="mt-1 text-sm text-zinc-500">NIP: {{ $person['nip'] }} · Pangkat/Gol.: {{ $person['pangkat'] }}</p>
            <p class="text-sm text-zinc-500">Jabatan: {{ $person['jabatan'] }}</p>
        </li>
    @endforeach
</ol>
