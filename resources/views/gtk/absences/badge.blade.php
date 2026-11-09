@php($color = ['pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'cancelled' => 'zinc'][$absence->status] ?? 'zinc')
<flux:badge size="sm" :color="$color">{{ \App\Models\GtkAbsenceRequest::STATUSES[$absence->status] ?? $absence->status }}</flux:badge>
