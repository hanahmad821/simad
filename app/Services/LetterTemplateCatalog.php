<?php

namespace App\Services;

use App\Models\LetterType;
use Illuminate\Support\Facades\View;

/**
 * @phpstan-type LetterViewTemplate array{name: string, description: string, fields: list<array{name: string, label: string, type: string, required: bool}>, uses_body: bool, default_body?: string}
 */
class LetterTemplateCatalog
{
    /** @return array<string, LetterViewTemplate> */
    public function all(): array
    {
        return array_filter(config('letter_templates', []), fn ($template, $view) => View::exists($view), ARRAY_FILTER_USE_BOTH);
    }

    /** @return LetterViewTemplate|null */
    public function find(?string $view): ?array
    {
        return $this->all()[$view ?? ''] ?? null;
    }

    public function availableFor(LetterType $type): bool
    {
        return $type->body_html === null && $this->find($type->template_view) !== null;
    }

    /** @return list<array{name: string, label: string, type: string, required: bool}> */
    public function fieldsFor(LetterType $type): array
    {
        return $this->find($type->template_view)['fields'] ?? [];
    }

    public function usesBody(?string $view): bool
    {
        return $this->find($view)['uses_body'] ?? true;
    }
}
