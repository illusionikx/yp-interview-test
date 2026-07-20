<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class EnrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Deliberately `return true;` — and this IS legitimate, unlike a
     * "helpfully" skipped ownership check: a student applying to a
     * section is acting on their own behalf only, so there is no
     * per-record ownership to verify here (contrast with the lecturer-side
     * writes in 08-05, which are SEC-03 ownership-gated). The `role:student`
     * route-group middleware is the sole access gate for this action.
     *
     * The window/capacity/ENR-04 checks are NOT moved here even though this
     * is a Form Request: they all require a LOCKED read
     * (`Section::lockForUpdate()`) to be correct under concurrency, and a
     * Form Request's authorize()/rules() run outside any such transaction.
     * They belong exclusively inside EnrollmentController@store's
     * DB::transaction() closure. Do not "helpfully" move them here — doing
     * so would silently break the ENR-02 capacity-safety guarantee.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Empty on purpose — there is no client-supplied input to validate.
     * `section_id` comes from route-model binding and `user_id` from the
     * authenticated user; nothing else is ever read from the request body.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
