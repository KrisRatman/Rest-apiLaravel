<?php

namespace App\Http\Requests\Team;

use App\Models\Team;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('transferOwnership', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::notIn([$team?->owner_id]),
                Rule::exists('team_user', 'user_id')->where('team_id', $team?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.not_in' => 'You already own this team.',
            'user_id.exists' => 'The new owner must be a member of the team.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'user_id' => ['description' => 'ID участника команды, который станет владельцем.', 'example' => 2],
        ];
    }
}
