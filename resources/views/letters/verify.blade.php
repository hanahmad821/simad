<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verifikasi Surat</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-zinc-100 dark:bg-zinc-950">

    <div class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-10">

        <div class="w-full">

            @if ($letter)

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">

                    {{-- Header --}}
                    <div class="border-b border-zinc-200 px-6 py-6 text-center dark:border-zinc-800">

                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-950">
                            <svg
                                class="size-8 text-green-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>

                        <h1 class="mt-4 text-xl font-bold text-zinc-900 dark:text-white">
                            Surat Terverifikasi
                        </h1>

                        <p class="mt-1 text-sm text-zinc-500">
                            Dokumen ini tercatat sebagai surat resmi
                            {{ $letter->letterType?->name ?? 'surat keluar' }}.
                        </p>

                    </div>

                    {{-- Detail --}}
                    <div class="space-y-5 p-6">

                        <div>
                            <div class="text-sm text-zinc-500">
                                Nomor Surat
                            </div>

                            <div class="mt-1 font-semibold text-zinc-900 dark:text-white">
                                {{ $letter->nomor_surat }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-zinc-500">
                                Jenis Surat
                            </div>

                            <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                                {{ $letter->letterType?->name ?? $letter->jenis_surat }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-zinc-500">
                                Tanggal Surat
                            </div>

                            <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                                {{ $letter->tanggal_surat?->translatedFormat('d F Y') }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-zinc-500">
                                Perihal
                            </div>

                            <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                                {{ $letter->perihal }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-zinc-500">
                                Tujuan
                            </div>

                            <div class="mt-1 font-medium text-zinc-900 dark:text-white">
                                {{ $letter->tujuan }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-green-50 p-4 dark:bg-green-950/30">

                            <div class="text-sm font-medium text-green-800 dark:text-green-300">
                                Status
                            </div>

                            <div class="mt-1 text-sm text-green-700 dark:text-green-400">
                                Surat telah disetujui dan diterbitkan secara resmi.
                            </div>

                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-zinc-200 px-6 py-4 text-center dark:border-zinc-800">

                        <p class="text-xs text-zinc-500">
                            Verifikasi dilakukan melalui sistem persuratan
                            {{ $letter->creator?->name ? '' : '' }}
                        </p>

                    </div>

                </div>

            @else

                <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">

                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950">
                        <svg
                            class="size-8 text-red-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </div>

                    <h1 class="mt-4 text-xl font-bold text-zinc-900 dark:text-white">
                        Surat Tidak Ditemukan
                    </h1>

                    <p class="mt-2 text-sm text-zinc-500">
                        Nomor verifikasi tidak ditemukan atau surat belum
                        diterbitkan secara resmi.
                    </p>

                </div>

            @endif

        </div>

    </div>

</body>

</html>
