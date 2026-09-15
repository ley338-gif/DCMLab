<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // review_reminders_enabled (W7, ADR 0084) gehoert nicht zu den
        // gemeinsam mit der Registrierung genutzten profileRules() --
        // ein neues Konto braucht diese Einstellung nicht im Formular.
        return [...$this->profileRules($this->user()->id), 'review_reminders_enabled' => 'boolean'];
    }
}
