<?php

use Livewire\Component;

new class extends Component {
    public function deleteUser(): void
    {
        abort(403, 'Pengelolaan akun hanya dapat dilakukan oleh admin.');
    }
}; ?>

<div></div>
