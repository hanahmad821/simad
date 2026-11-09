@if(session('status'))
    <div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
        <p class="font-semibold">Periksa kembali data berikut:</p>
        <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
