<?php

namespace App\Http\Controllers\Api\Gpt\V1\Freelance;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Contact;
use App\Models\FreelanceProject;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreelanceProjectController extends GptController
{
    private const STATUSES   = ['lead', 'proposal', 'active', 'on_hold', 'completed', 'cancelled'];
    private const RATE_TYPES = ['hourly', 'fixed'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'         => 'nullable|string|in:' . implode(',', self::STATUSES),
            'platform'       => 'nullable|string|max:100',
            'contact_id'     => 'nullable|integer',
            'opportunity_id' => 'nullable|integer',
            'search'         => 'nullable|string|max:200',
            'limit'          => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = FreelanceProject::where('user_id', $user->id)->with(['contact', 'opportunity']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($opportunityId = $request->input('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('client_name', 'like', '%' . $search . '%');
            });
        }

        $projects = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $projects->map(fn ($p) => $this->format($p)),
            'count' => $projects->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:500',
            'contact_id'      => 'nullable|integer',
            'opportunity_id'  => 'nullable|integer',
            'client_name'     => 'nullable|string|max:255',
            'platform'        => 'nullable|string|max:100',
            'status'          => 'nullable|in:' . implode(',', self::STATUSES),
            'rate_type'       => 'nullable|in:' . implode(',', self::RATE_TYPES),
            'rate'            => 'nullable|numeric|min:0',
            'budget'          => 'nullable|numeric|min:0',
            'currency'        => 'nullable|string|size:3',
            'estimated_hours' => 'nullable|numeric|min:0',
            'hours_logged'    => 'nullable|numeric|min:0',
            'description'     => 'nullable|string|max:100000',
            'url'             => 'nullable|url|max:2000',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'meta'            => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        // Validate ownership of linked entities before associating.
        $contact     = $this->resolveContact($user->id, $data['contact_id'] ?? null);
        $opportunity = $this->resolveOpportunity($user->id, $data['opportunity_id'] ?? null);

        $project = FreelanceProject::create([
            'user_id'         => $user->id,
            'tenant_id'       => $user->tenant_id,
            'contact_id'      => $contact?->id,
            'opportunity_id'  => $opportunity?->id,
            'title'           => $data['title'],
            'client_name'     => $data['client_name'] ?? null,
            'platform'        => $data['platform'] ?? null,
            'status'          => $data['status'] ?? 'lead',
            'rate_type'       => $data['rate_type'] ?? null,
            'rate'            => $data['rate'] ?? null,
            'budget'          => $data['budget'] ?? null,
            'currency'        => strtoupper($data['currency'] ?? 'USD'),
            'estimated_hours' => $data['estimated_hours'] ?? null,
            'hours_logged'    => $data['hours_logged'] ?? 0,
            'description'     => $data['description'] ?? null,
            'url'             => $data['url'] ?? null,
            'start_date'      => $data['start_date'] ?? null,
            'due_date'        => $data['due_date'] ?? null,
            'meta'            => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_freelance_project', 'freelance_project', $project->id, 'low',
            "title={$project->title}, status={$project->status}", "id={$project->id}");

        $project->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($project)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = FreelanceProject::where('user_id', $this->apiUser($request)->id)
            ->with(['contact', 'opportunity'])
            ->findOrFail($id);

        return response()->json(['data' => $this->format($project)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'sometimes|string|max:500',
            'contact_id'      => 'sometimes|nullable|integer',
            'opportunity_id'  => 'sometimes|nullable|integer',
            'client_name'     => 'sometimes|nullable|string|max:255',
            'platform'        => 'sometimes|nullable|string|max:100',
            'status'          => 'sometimes|in:' . implode(',', self::STATUSES),
            'rate_type'       => 'sometimes|nullable|in:' . implode(',', self::RATE_TYPES),
            'rate'            => 'sometimes|nullable|numeric|min:0',
            'budget'          => 'sometimes|nullable|numeric|min:0',
            'currency'        => 'sometimes|string|size:3',
            'estimated_hours' => 'sometimes|nullable|numeric|min:0',
            'hours_logged'    => 'sometimes|nullable|numeric|min:0',
            'description'     => 'sometimes|nullable|string|max:100000',
            'url'             => 'sometimes|nullable|url|max:2000',
            'start_date'      => 'sometimes|nullable|date',
            'due_date'        => 'sometimes|nullable|date',
            'meta'            => 'sometimes|nullable|array',
        ]);

        $user    = $this->apiUser($request);
        $project = FreelanceProject::where('user_id', $user->id)->findOrFail($id);

        if (empty($data)) {
            return response()->json(['error' => 'No updatable fields provided.'], 422);
        }

        if (array_key_exists('contact_id', $data)) {
            $project->contact_id = $this->resolveContact($user->id, $data['contact_id'])?->id;
        }
        if (array_key_exists('opportunity_id', $data)) {
            $project->opportunity_id = $this->resolveOpportunity($user->id, $data['opportunity_id'])?->id;
        }
        if (array_key_exists('currency', $data)) {
            $project->currency = strtoupper($data['currency']);
        }
        foreach (['title', 'client_name', 'platform', 'status', 'rate_type', 'rate', 'budget', 'estimated_hours', 'hours_logged', 'description', 'url', 'start_date', 'due_date', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $project->{$field} = $data[$field];
            }
        }

        // Keep completed_at in sync with explicit status transitions.
        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'completed' && ! $project->completed_at) {
                $project->completed_at = now();
            } elseif ($data['status'] !== 'completed') {
                $project->completed_at = null;
            }
        }

        $project->save();

        $this->audit($request, 'update_freelance_project', 'freelance_project', $project->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$project->id}");

        $project->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($project)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = FreelanceProject::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $project->delete();

        $this->audit($request, 'delete_freelance_project', 'freelance_project', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Mark a project as completed and stamp the completion time. Rejects if the
     * project is already completed or cancelled.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $user    = $this->apiUser($request);
        $project = FreelanceProject::where('user_id', $user->id)->findOrFail($id);

        if (in_array($project->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'error'  => "Project cannot be completed from status '{$project->status}'.",
                'status' => $project->status,
            ], 422);
        }

        $project->status       = 'completed';
        $project->completed_at = now();
        $project->save();

        $this->audit($request, 'complete_freelance_project', 'freelance_project', $project->id, 'medium',
            "id={$project->id}", "completed_at={$project->completed_at}");

        $project->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($project)]);
    }

    private function resolveContact(int $userId, ?int $contactId): ?Contact
    {
        if (empty($contactId)) {
            return null;
        }

        return Contact::where('user_id', $userId)->findOrFail($contactId);
    }

    private function resolveOpportunity(int $userId, ?int $opportunityId): ?Opportunity
    {
        if (empty($opportunityId)) {
            return null;
        }

        return Opportunity::where('user_id', $userId)->findOrFail($opportunityId);
    }

    public function format(FreelanceProject $p): array
    {
        return [
            'id'              => $p->id,
            'title'           => $p->title,
            'client_name'     => $p->client_name,
            'platform'        => $p->platform,
            'status'          => $p->status,
            'rate_type'       => $p->rate_type,
            'rate'            => $p->rate,
            'budget'          => $p->budget,
            'currency'        => $p->currency,
            'estimated_hours' => $p->estimated_hours,
            'hours_logged'    => $p->hours_logged,
            'description'     => $p->description,
            'url'             => $p->url,
            'start_date'      => $p->start_date?->toDateString(),
            'due_date'        => $p->due_date?->toDateString(),
            'completed_at'    => $p->completed_at?->toISOString(),
            'meta'            => $p->meta,
            'contact_id'      => $p->contact_id,
            'opportunity_id'  => $p->opportunity_id,
            'contact'         => $p->relationLoaded('contact') ? $p->contact?->only(['id', 'first_name', 'last_name', 'email']) : null,
            'opportunity'     => $p->relationLoaded('opportunity') ? $p->opportunity?->only(['id', 'title', 'status']) : null,
            'created_at'      => $p->created_at?->toISOString(),
            'updated_at'      => $p->updated_at?->toISOString(),
        ];
    }
}
