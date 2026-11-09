<?php

namespace App\Livewire\Concerns;

use App\Services\AssignmentLetterService;
use App\Services\LetterTemplateService;
use Livewire\Attributes\Computed;

trait HasAssignmentRecipients
{
    #[Computed]
    public function isAssignmentLetter(): bool
    {
        if (!(isset($this->outgoingLetter) ? $this->outgoingLetter->use_template : $this->useTemplate)) return false;
        $view = isset($this->outgoingLetter)
            ? (app(LetterTemplateService::class)->definition($this->outgoingLetter)['template_view'] ?? null)
            : $this->selectedLetterType?->template_view;

        return $view === 'pdf.letters.tugas';
    }

    protected function prepareAssignmentTarget(): void
    {
        if ($this->isAssignmentLetter && (isset($this->outgoingLetter) ? $this->outgoingLetter->use_template : $this->useTemplate)) {
            $this->tujuan = app(AssignmentLetterService::class)->recipientSummary($this->formData);
        }
    }

    public function addAssignmentRecipient(string $field): void
    {
        $this->assertAssignmentField($field);
        $rows = is_array($this->formData[$field] ?? null) ? $this->formData[$field] : [];
        if (count($rows) >= 20) {
            $this->addError('formData.'.$field, 'Maksimal 20 penerima tugas per surat.');
            return;
        }
        $rows[] = ['gtk_id' => '', 'pangkat' => ''];
        $this->formData[$field] = $rows;
    }

    public function removeAssignmentRecipient(string $field, int $index): void
    {
        $this->assertAssignmentField($field);
        $rows = is_array($this->formData[$field] ?? null) ? $this->formData[$field] : [];
        unset($rows[$index]);
        $this->formData[$field] = array_values($rows);
        $this->resetValidation(['formData.'.$field, 'formData.'.$field.'.*']);
    }

    private function assertAssignmentField(string $field): void
    {
        abort_unless(collect($this->formFields)->contains(fn ($definition) => $definition['name'] === $field && $definition['type'] === 'gtk_assignments'), 422);
    }
}
