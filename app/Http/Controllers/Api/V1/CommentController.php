<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Комментарии', 'Обсуждение задачи. Писать может любой участник команды, править — только автор, удалять — автор, владелец и админы.')]
class CommentController extends Controller
{
    #[Endpoint('Комментарии задачи', 'От старых к новым.')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 30)]
    #[ResponseFromApiResource(CommentResource::class, Comment::class, collection: true, paginate: 30, with: ['author'])]
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Comment::class, $task]);

        $comments = $task->comments()
            ->with('author')
            ->oldest()
            ->oldest('id')
            ->paginate($this->perPage($request, 30));

        return CommentResource::collection($comments);
    }

    #[Endpoint('Написать комментарий')]
    #[ResponseFromApiResource(CommentResource::class, Comment::class, 201, with: ['author'])]
    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = new Comment($request->validated());
        $comment->task()->associate($task);
        $comment->author()->associate($request->user());
        $comment->save();

        return CommentResource::make($comment->load('author'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Изменить комментарий')]
    #[ResponseFromApiResource(CommentResource::class, Comment::class, with: ['author'])]
    #[ScribeResponse(['message' => 'This action is unauthorized.'], 403, 'Чужой комментарий')]
    public function update(UpdateCommentRequest $request, Comment $comment): CommentResource
    {
        $comment->update($request->validated());

        return CommentResource::make($comment->load('author'));
    }

    #[Endpoint('Удалить комментарий')]
    #[ScribeResponse(status: 204)]
    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
