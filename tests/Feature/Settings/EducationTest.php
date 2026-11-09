<?php

use App\Models\Gtk;
use App\Models\User;
use Livewire\Livewire;

test('users can manage their own education history', function () {
    $gtk = Gtk::create(['full_name' => 'Guru']);
    $this->actingAs(User::factory()->create(['gtk_id' => $gtk->id]));

    $component = Livewire::test('pages::settings.education')
        ->assertSee('Belum ada riwayat pendidikan.')
        ->call('create')
        ->set('level', 'S1')
        ->set('institution_name', 'Universitas A')
        ->set('start_year', '2010')
        ->set('graduation_year', '2014')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Universitas A');

    $education = $gtk->educations()->sole();
    $component->call('edit', $education->id)
        ->assertSet('institution_name', 'Universitas A')
        ->set('major', 'Pendidikan')
        ->call('save')
        ->assertHasNoErrors();

    expect($education->refresh()->major)->toBe('Pendidikan');
    $component->call('delete', $education->id)
        ->assertSee('Belum ada riwayat pendidikan.');
    $this->assertDatabaseMissing('gtk_educations', ['id' => $education->id]);
});

test('users cannot view or modify another gtk education', function (string $action) {
    $ownGtk = Gtk::create(['full_name' => 'Guru A']);
    $otherGtk = Gtk::create(['full_name' => 'Guru B']);
    $education = $otherGtk->educations()->create(['level' => 'S1', 'institution_name' => 'Institusi Rahasia']);
    $this->actingAs(User::factory()->create(['gtk_id' => $ownGtk->id]));

    $component = Livewire::test('pages::settings.education')
        ->assertDontSee('Institusi Rahasia');

    if ($action === 'save') {
        $component->set('editingId', $education->id)->call('save')->assertNotFound();
    } else {
        $component->call($action, $education->id)->assertNotFound();
    }

    expect($education->fresh()->institution_name)->toBe('Institusi Rahasia');
})->with(['edit', 'save', 'delete']);

test('education validates required fields and year order', function () {
    $gtk = Gtk::create(['full_name' => 'Guru']);
    $this->actingAs(User::factory()->create(['gtk_id' => $gtk->id]));

    Livewire::test('pages::settings.education')
        ->set('start_year', '2014')
        ->set('graduation_year', '2010')
        ->call('save')
        ->assertHasErrors(['level', 'institution_name', 'graduation_year']);

    expect($gtk->educations()->count())->toBe(0);
});

test('users without linked gtk cannot access education history', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.education')->assertForbidden();
});
