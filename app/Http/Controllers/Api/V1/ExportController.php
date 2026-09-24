<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\StartExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\StoreExportRequest;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Models\Project;
use App\Queries\TaskListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Экспорт', 'Выгрузка задач проекта в CSV. Файл собирается в фоне: запрос сразу отвечает 202, статус можно опрашивать, а по готовности приходит письмо. Выгрузка видна только автору и хранится 7 дней.')]
class ExportController extends Controller
{
    private const EXPORT_EXAMPLE = [
        'id' => 1,
        'project_id' => 1,
        'status' => 'completed',
        'filters' => ['filter' => ['status' => 'todo,in_progress']],
        'rows_count' => 42,
        'error' => null,
        'download_url' => 'https://api.example.com/api/v1/exports/1/download',
        'created_at' => '2026-09-24T10:00:00.000000Z',
        'finished_at' => '2026-09-24T10:00:03.000000Z',
    ];

    #[Endpoint('Мои выгрузки', 'Новые сверху.')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 15)]
    #[ScribeResponse(['data' => [self::EXPORT_EXAMPLE], 'links' => ['first' => '…', 'last' => '…', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $exports = Export::query()
            ->whereBelongsTo($request->user())
            ->latest()
            ->latest('id')
            ->paginate($this->perPage($request));

        return ExportResource::collection($exports);
    }

    #[Endpoint('Запустить выгрузку', 'Ставит сборку CSV в очередь и сразу возвращает выгрузку со статусом `pending`. Неизвестный фильтр или сортировка → 400 ещё до постановки в очередь.')]
    #[ScribeResponse(['data' => ['status' => 'pending', 'rows_count' => null, 'download_url' => null, 'finished_at' => null] + self::EXPORT_EXAMPLE], 202)]
    public function store(StoreExportRequest $request, Project $project, StartExport $startExport): JsonResponse
    {
        // Сборка запроса проверяет имена фильтров и сортировок (неизвестные → 400),
        // чтобы ошибка пришла клиенту сразу, а не упала потом в очереди.
        TaskListQuery::for($project->tasks(), $request);

        $export = $startExport->handle($project, $request->user(), $request->exportFilters());

        return ExportResource::make($export)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    #[Endpoint('Статус выгрузки', '`pending` → `processing` → `completed` или `failed`. Когда готово, появляется `download_url`.')]
    #[ScribeResponse(['data' => self::EXPORT_EXAMPLE])]
    public function show(Export $export): ExportResource
    {
        Gate::authorize('view', $export);

        return ExportResource::make($export);
    }

    #[Endpoint('Скачать CSV', 'UTF-8 с BOM, открывается в Excel. Пока файл не готов — 409.')]
    #[ScribeResponse("ID,Title,Status,Priority,Assignee,Assignee email,Due date,Labels,Comments,Created at,Completed at\n7,Apple Pay integration,in_progress,urgent,Demo User,demo@example.com,2026-09-29,\"feature, backend\",2,2026-09-24 10:00:00,", 200, 'CSV-файл')]
    #[ScribeResponse(['message' => 'The export is not ready yet.'], 409, 'Файл ещё собирается')]
    public function download(Export $export): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $export);

        if (! $export->isCompleted()) {
            return response()->json(['message' => 'The export is not ready yet.'], Response::HTTP_CONFLICT);
        }

        return Storage::disk('local')->download($export->file_path, $export->fileName(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
