<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin dashboard CRUD for the static CMS sections consumed by the mobile
 * app: privacy policy, about, and support. All sections share the same
 * shape ({ id, title, description }) and the same table (`app_policies`)
 * but are partitioned by a `type` column.
 *
 * Routes:
 *   /api/admin/app-policies          ← legacy alias (defaults to type=privacy when missing)
 *   /api/admin/app-sections          ← preferred, generic CRUD
 *
 * The Vercel admin settings UI should render one tab per `type`
 * (privacy | about | support) and call the same endpoints.
 */
class AdminAppPolicyController extends Controller
{
    /**
     * Paginated list (admin sees ALL rows, including inactive ones).
     *
     * Query params:
     *   - type: privacy|about|support (optional, filters rows)
     *   - q: free-text search across title/description (ar + en)
     *   - is_active: boolean (optional)
     *   - per_page: 1..100 (default 50 so the admin sees all sections at once)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 50)));

        $query = AppPolicy::query()
            ->orderBy('type')
            ->orderBy('order_index')
            ->orderBy('id');

        if ($request->filled('type')) {
            $query->where('type', $this->normaliseType($request->get('type')));
        }
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
                'types' => AppPolicy::TYPES,
                'counts' => $this->counts(),
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
        $validated = $this->validatePayload($request);

        $policy = AppPolicy::create($validated);

        return response()->json([
            'message' => 'Section created successfully',
            'data' => $this->toArray($policy),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $policy = AppPolicy::findOrFail($id);
        $validated = $this->validatePayload($request, $policy);

        $policy->update($validated);

        return response()->json([
            'message' => 'Section updated successfully',
            'data' => $this->toArray($policy->fresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $policy = AppPolicy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'message' => 'Section deleted successfully',
        ]);
    }

    /**
     * Bulk update display order, optionally scoped to a single type.
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
            'message' => 'Order updated successfully',
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
            'type' => (string) ($p->type ?? AppPolicy::TYPE_PRIVACY),
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
     * Shared validation for create/update.
     *
     * On create, `type` is required. On update, `type` is optional and
     * defaults to the existing value so the admin UI can patch a title or
     * description without resending the type.
     *
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, ?AppPolicy $existing = null): array
    {
        $typeRule = $existing
            ? ['sometimes', 'string', Rule::in(AppPolicy::TYPES)]
            : ['required', 'string', Rule::in(AppPolicy::TYPES)];

        $data = $request->validate([
            'type' => $typeRule,
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (! isset($data['type']) && $existing) {
            $data['type'] = $existing->type;
        }
        if (isset($data['type'])) {
            $data['type'] = $this->normaliseType($data['type']);
        }

        return $data;
    }

    /**
     * Accept a few common aliases from the admin UI and normalise them to
     * the canonical type values used by the mobile app.
     */
    protected function normaliseType(mixed $raw): string
    {
        $v = strtolower(trim((string) $raw));

        return match ($v) {
            'policy', 'privacy_policy', 'privacy-policy'                   => AppPolicy::TYPE_PRIVACY,
            'about_app', 'about-app', 'app_about'                          => AppPolicy::TYPE_ABOUT,
            'help', 'help_support', 'contact'                               => AppPolicy::TYPE_SUPPORT,
            'terms_of_use', 'terms-of-use', 'usage_terms', 'شروط_الاستخدام' => AppPolicy::TYPE_TERMS,
            'merchant-terms', 'merchant_term', 'شروط_التاجر'                => AppPolicy::TYPE_MERCHANT_TERMS,
            'platform-rules', 'platform_rule', 'قواعد_المنصة'               => AppPolicy::TYPE_PLATFORM_RULES,
            default                                                          => in_array($v, AppPolicy::TYPES, true)
                ? $v
                : AppPolicy::TYPE_PRIVACY,
        };
    }

    /**
     * Number of rows per type — useful for the settings page to show badges.
     *
     * @return array<string, int>
     */
    protected function counts(): array
    {
        $rows = AppPolicy::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $out = [];
        foreach (AppPolicy::TYPES as $t) {
            $out[$t] = (int) ($rows[$t] ?? 0);
        }

        return $out;
    }
}
