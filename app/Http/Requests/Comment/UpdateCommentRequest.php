<?php

namespace App\Http\Requests\Comment;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('comment'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'body' => ['description' => 'Новый текст комментария.', 'example' => 'Макет обновил ещё раз'],
        ];
    }
}
