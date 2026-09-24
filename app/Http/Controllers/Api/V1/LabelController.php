<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Label\StoreLabelRequest;
use App\Http\Requests\Label\UpdateLabelRequest;
use App\Http\Resources\LabelResource;
use App\Models\Label;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Метки', 'Цветные метки команды для задач. Видят все участники, управляют владелец и админы.')]
class LabelController extends Controller
{
    #[Endpoint('Метки команды', 'Полный список без пагинации — меток в команде немного.')]
    #[ResponseFromApiResource(LabelResource::class, Label::class, collection: true)]
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Label::class, $team]);

        return LabelResource::collection($team->labels()->orderBy('name')->get());
    }

    #[Endpoint('Создать метку')]
    #[ResponseFromApiResource(LabelResource::class, Label::class, 201)]
    public function store(StoreLabelRequest $request, Team $team): JsonResponse
    {
        $label = $team->labels()->create($request->validated());

        return LabelResource::make($label)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Изменить метку')]
    #[ResponseFromApiResource(LabelResource::class, Label::class)]
    public function update(UpdateLabelRequest $request, Label $label): LabelResource
    {
        $label->update($request->validated());

        return LabelResource::make($label);
    }

    #[Endpoint('Удалить метку', 'Метка снимается со всех задач.')]
    #[ScribeResponse(status: 204)]
    public function destroy(Label $label): Response
    {
        Gate::authorize('delete', $label);

        $label->delete();

        return response()->noContent();
    }
}
