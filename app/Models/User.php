<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string $role
 * @property string|null $secondary_role
 * @property int|null $gtk_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'secondary_role', 'gtk_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    public const ROLE_LABELS = [
        'admin' => 'Admin',
        'headmaster' => 'Kepala Madrasah',
        'teacher' => 'Guru',
        'staff' => 'Staf TU',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $attributes = ['role' => 'staff'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** @return HasMany<StudentAttendance, $this> */
    public function recordedStudentAttendances(): HasMany
    {
        return $this->hasMany(
            StudentAttendance::class,
            'recorded_by'
        );
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_values(array_unique(array_filter([$this->role, $this->secondary_role],
            fn ($role) => isset(self::ROLE_LABELS[$role]))));
    }

    /** @param string|list<string> $roles */
    public function hasRole(string|array $roles): bool
    {
        return array_intersect($this->roleNames(), (array) $roles) !== [];
    }

    public function activeRole(): string
    {
        if (auth()->id() === $this->id) {
            $context = session('active_role');
            if (is_array($context) && ($context['user_id'] ?? null) === $this->id
                && is_string($context['role'] ?? null) && $this->hasRole($context['role'])) {
                return $context['role'];
            }
        }

        return $this->role;
    }

    /** @param string|list<string> $roles */
    public function hasActiveRole(string|array $roles): bool
    {
        return in_array($this->activeRole(), (array) $roles, true);
    }

    /**
     * @param  Builder<User>  $query
     * @param  list<string>  $roles
     * @return Builder<User>
     */
    public function scopeWithRoles(Builder $query, array $roles): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereIn('role', $roles)->orWhereIn('secondary_role', $roles));
    }

    public function isAdmin(): bool
    {
        return $this->hasActiveRole('admin');
    }

    public function canManageLetters(): bool
    {
        return $this->hasActiveRole(['admin', 'staff']);
    }

    public function isHeadmaster(): bool
    {
        return $this->hasActiveRole('headmaster');
    }

    public function isTeacher(): bool
    {
        return $this->hasActiveRole('teacher');
    }

    public function canTeach(): bool
    {
        return $this->hasActiveRole(['teacher', 'headmaster']);
    }

    public function isStaff(): bool
    {
        return $this->hasActiveRole('staff');
    }

    /** @return HasMany<LetterDisposition, $this> */
    public function sentDispositions(): HasMany
    {
        return $this->hasMany(
            LetterDisposition::class,
            'from_user_id'
        );
    }

    /** @return HasMany<LetterDisposition, $this> */
    public function receivedDispositions(): HasMany
    {
        return $this->hasMany(
            LetterDisposition::class,
            'to_user_id'
        );
    }

    /** @return HasMany<OutgoingLetter, $this> */
    public function outgoingLetters(): HasMany
    {
        return $this->hasMany(
            OutgoingLetter::class,
            'created_by'
        );
    }
}
