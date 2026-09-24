<?php

namespace App\Http\Requests\Comment;

use App\Models\Comment;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', [Comment::class, $this->route('task')]);
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
            'body' => ['description' => 'Текст комментария.', 'example' => 'Макет обновил, можно брать в работу'],
        ];
    }
}
