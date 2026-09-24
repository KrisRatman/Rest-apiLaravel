<?php

namespace App\Http\Resources;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'status' => $this->status,
            'filters' => (object) ($this->filters ?? []),
            'rows_count' => $this->rows_count,
            'error' => $this->error,
            'download_url' => $this->isCompleted() ? route('v1.exports.download', $this->resource) : null,
            'created_at' => $this->created_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
