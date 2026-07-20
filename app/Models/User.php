<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * NOTE (T-01-02-MA): `role` is fillable for server-controlled writes
     * only (seeder, factories, tests). No public-facing controller may
     * build a User from request-sourced input for this attribute —
     * registration (01-04) must set `role` from a hardcoded server
     * constant, never `$request->role`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'role' => Role::class,
        ];
    }

    /**
     * The sections this user (a student) is enrolled in, via the
     * enrollments pivot (SEC-01/ENR-08). Uses the custom Enrollment pivot
     * so pivot->status is cast to EnrollmentStatus.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'enrollments')
            ->using(Enrollment::class)
            ->withPivot(['status', 'rejection_reason'])
            ->withTimestamps();
    }

    /**
     * The subjects this user (a lecturer) is assigned to manage (SEC-03).
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user');
    }

    public function isLecturer(): bool
    {
        return $this->role === Role::Lecturer;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }
}
