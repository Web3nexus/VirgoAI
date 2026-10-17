<?php

namespace App\Http\Requests;

use App\Enums\AutomationMode;
use App\Workflows\WorkflowTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_key' => ['nullable', 'string', Rule::in(array_keys(WorkflowTemplates::all()))],
            'mode' => ['nullable', Rule::enum(AutomationMode::class)],
        ];
    }
}
