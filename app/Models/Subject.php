<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * subject_user pivot — the lecturers assigned to manage this subject
     * (SEC-03). Any assigned lecturer, not only the exam's creator, may
     * manage this subject's sections/enrollments/exams.
     */
    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
