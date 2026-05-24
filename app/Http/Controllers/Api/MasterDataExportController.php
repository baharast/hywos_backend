<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExportJobStatus;
use App\Http\Requests\MasterDataExport\CreateExportJobRequest;
use App\Http\Resources\ExportJobResource;
use App\Models\ExportJob;
use App\Services\ApiResponse;
use App\Services\MasterDataExport\MasterDataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDataExportController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        $query = ExportJob::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('format')) {
            $query->where('format', $request->query('format'));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($s): void {
                $q->where('display_name', 'like', $s)
                  ->orWhere('requested_by_name', 'like', $s);
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $items = ExportJobResource::collection($paginator->items());

        $summary = [
            'totalJobs' => ExportJob::count(),
            'readyJobs' => ExportJob::where('status', ExportJobStatus::READY)->count(),
            'queuedOrGenerating' => ExportJob::whereIn('status', [
                ExportJobStatus::QUEUED,
                ExportJobStatus::GENERATING,
            ])->count(),
            'failedJobs' => ExportJob::where('status', ExportJobStatus::FAILED)->count(),
        ];

        return ApiResponse::list(
            $items,
            $paginator,
            $summary,
            ExportJob::max('updated_at'),
            'Export jobs retrieved'
        );
    }

    public function store(CreateExportJobRequest $request, MasterDataExportService $service)
    {
        $setup = $request->validated();
        $job = $service->create($setup);

        // Synchronous generation in dev — no queue worker required. When a queue
        // is wired up later, swap this for `ProcessExportJob::dispatch($job->id)`.
        $job = $service->generate($job);

        $code = $job->status === ExportJobStatus::READY ? 201 : 202;
        return $this->success(
            new ExportJobResource($job),
            $job->status === ExportJobStatus::READY
                ? 'Export ready'
                : 'Export job created',
            $code
        );
    }

    public function show($id)
    {
        $job = ExportJob::find($id);
        if (! $job) {
            return $this->error('Export job not found', 'EXPORT_JOB_NOT_FOUND', 404);
        }
        return $this->success(new ExportJobResource($job), 'Export job retrieved');
    }

    public function download($id, MasterDataExportService $service)
    {
        $job = ExportJob::find($id);
        if (! $job) {
            return $this->error('Export job not found', 'EXPORT_JOB_NOT_FOUND', 404);
        }
        if ($job->status !== ExportJobStatus::READY) {
            return $this->error('Export not ready', 'EXPORT_NOT_READY', 409, [
                'status' => $job->status,
            ]);
        }
        if ($job->isExpired()) {
            return $this->error('Export has expired', 'EXPORT_EXPIRED', 410);
        }
        if (! $job->file_path || ! $job->file_disk) {
            return $this->error('Export file is missing', 'EXPORT_FILE_MISSING', 410);
        }
        $disk = Storage::disk($job->file_disk);
        if (! $disk->exists($job->file_path)) {
            return $this->error('Export file is missing', 'EXPORT_FILE_MISSING', 410);
        }

        $service->recordDownload($job);

        $filename = "master-data-export-{$job->id}.csv";
        return $disk->download($job->file_path, $filename);
    }

    public function retry($id, MasterDataExportService $service)
    {
        $original = ExportJob::find($id);
        if (! $original) {
            return $this->error('Export job not found', 'EXPORT_JOB_NOT_FOUND', 404);
        }
        if ($original->status !== ExportJobStatus::FAILED) {
            return $this->error('Only failed jobs can be retried', 'EXPORT_NOT_RETRYABLE', 409, [
                'status' => $original->status,
            ]);
        }

        $retry = $service->create([
            'categories' => $original->categories,
            'record_scope' => $original->record_scope,
            'date_from' => $original->date_from,
            'date_to'   => $original->date_to,
            'status_scope' => $original->status_scope,
            'field_set' => $original->field_set,
            'format' => $original->format,
            'requested_by_name' => $original->requested_by_name,
        ]);
        $retry = $service->generate($retry);
        $service->recordRetry($original, $retry);

        return $this->success(new ExportJobResource($retry), 'Export retried', 201);
    }
}
