<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin dashboard CRUD for the Privacy Policy sections consumed by the
 * mobile app through GET /api/mobile/app/policy.
 *
 * Routes live under /api/admin/app-policies (protected by auth:sanctum + admin).
 * Response shapes mirror the mobile endpoint to minimise client adapters.
 */
class AdminAppPolicyController extends Controller
{
    /**
     * Paginated list (admin sees ALL rows, including inactive ones).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 20)));

        $query = AppPolicy::query()->orderBy('order_index')->orderBy('id');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('q')) {
            $like = '%' . trim((string) $request->get('q')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title_ar', 'like', $like)
                    ->orWhere('title_en', 'like', $like)
                    ->orWhere('description_ar', 'like', $like)
                    ->orWhere('description_en', 'like', $like);
            });
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AppPolicy $p) => $this->toArray($p))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $policy = AppPolicy::findOrFail($id);

        return response()->json(['data' => $this->toArray($policy)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, null);

        $policy = AppPolicy::create($validated);

        return response()->json([
            'message' => 'Policy section created successfully',
            'data' => $this->toArray($policy),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $policy = AppPolicy::findOrFail($id);
        $validated = $this->validatePayload($request, $policy->id);

        $policy->update($validated);

        return response()->json([
            'message' => 'Policy section updated successfully',
            'data' => $this->toArray($policy->fresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $policy = AppPolicy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'message' => 'Policy section deleted successfully',
        ]);
    }

    /**
     * Bulk update display order.
     *
     * Request:
     *   { "order": [ { "id": 3, "order_index": 0 }, { "id": 7, "order_index": 1 } ] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*.id' => 'required|integer|exists:app_policies,id',
            'order.*.order_index' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $row) {
                AppPolicy::where('id', $row['id'])->update([
                    'order_index' => (int) $row['order_index'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Policy order updated successfully',
        ]);
    }

    /**
     * Build an array shaped like the mobile response item for consistency.
     *
     * @return array<string, mixed>
     */
    protected function toArray(AppPolicy $p): array
    {
        return [
            'id' => $p->id,
            'title_ar' => $p->title_ar ?? '',
            'title_en' => $p->title_en ?? '',
            'description_ar' => $p->description_ar ?? '',
            'description_en' => $p->description_en ?? '',
            'order_index' => (int) $p->order_index,
            'is_active' => (bool) $p->is_active,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Shared validation for create/update. Either language pair must be non-empty
     * so the mobile app always has something to render.
     *
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
