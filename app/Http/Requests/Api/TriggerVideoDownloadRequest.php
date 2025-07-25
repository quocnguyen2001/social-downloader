<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for triggering video download.
 */
class TriggerVideoDownloadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'download_option_id' => [
                'required',
                'string',
                'uuid',
                'exists:download_options,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'download_option_id.required' => __('validation.required', [
                'attribute' => __('models.download_option.fields.id'),
            ]),
            'download_option_id.string' => __('validation.string', [
                'attribute' => __('models.download_option.fields.id'),
            ]),
            'download_option_id.uuid' => __('validation.uuid', [
                'attribute' => __('models.download_option.fields.id'),
            ]),
            'download_option_id.exists' => __('validation.exists', [
                'attribute' => __('models.download_option.fields.id'),
            ]),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'download_option_id' => __('models.download_option.fields.id'),
        ];
    }
}
