@if (count(auth()->user()->roleNames()) > 1)
    <div class="px-2 py-1 text-xs text-zinc-500">Gunakan akun sebagai</div>
    @foreach (auth()->user()->roleNames() as $availableRole)
        <form method="POST" action="{{ route('role.switch') }}" class="w-full">
            @csrf
            <input type="hidden" name="role" value="{{ $availableRole }}">
            <flux:menu.item as="button" type="submit" class="w-full cursor-pointer"
                :icon="auth()->user()->activeRole() === $availableRole ? 'check' : 'arrow-path'"
                :disabled="auth()->user()->activeRole() === $availableRole">
                Gunakan sebagai {{ \App\Models\User::ROLE_LABELS[$availableRole] }}{{ auth()->user()->activeRole() === $availableRole ? ' (aktif)' : '' }}
            </flux:menu.item>
        </form>
    @endforeach
    <flux:menu.separator />
@endif
