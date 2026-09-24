<?php

namespace App\Http\Requests\Label;

use App\Models\Label;
use App\Models\Team;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', [Label::class, $this->route('team')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('labels')->where('team_id', $team?->id),
            ],
            'color' => ['required', 'string', 'hex_color', 'size:7'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Название метки, уникальное в команде.', 'example' => 'bug'],
            'color' => ['description' => 'Цвет в формате `#RRGGBB`.', 'example' => '#E5484D'],
        ];
    }
}
