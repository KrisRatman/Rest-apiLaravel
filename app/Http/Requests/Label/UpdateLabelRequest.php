<?php

namespace App\Http\Requests\Label;

use App\Models\Label;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('label'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Label|null $label */
        $label = $this->route('label');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('labels')->where('team_id', $label?->team_id)->ignore($label),
            ],
            'color' => ['sometimes', 'required', 'string', 'hex_color', 'size:7'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Новое название метки.', 'example' => 'critical-bug'],
            'color' => ['description' => 'Новый цвет `#RRGGBB`.', 'example' => '#FF0000'],
        ];
    }
}
