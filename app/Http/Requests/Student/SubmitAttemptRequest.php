<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * SHAPE validation only — ownership/deadline are the controller's
     * job via AttemptPolicy + Attempt::finalize() (mirrors this
     * codebase's AnswerRequest split, 04-RESEARCH.md Pattern 4). The
     * submit action carries no request body fields, so this Form
     * Request exists purely to preserve the project's one-Form-
     * Request-per-write-action convention (STACK.md section 6).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Submit accepts no body — the attempt id comes from the route
     * binding only, and finalization state is entirely server-derived
     * (never accept a client-supplied "time remaining"/status field).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
