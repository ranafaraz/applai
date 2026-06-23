<?php

namespace App\Http\Controllers\Api\Gpt\V1\Research;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\ResearchPaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchPaperController extends GptController
{
    private const STATUSES = ['to_read', 'reading', 'read', 'archived'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|string|in:' . implode(',', self::STATUSES),
            'venue'    => 'nullable|string|max:200',
            'arxiv_id' => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:200',
            'limit'    => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = ResearchPaper::where('user_id', $user->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($venue = $request->input('venue')) {
            $query->where('venue', $venue);
        }
        if ($arxivId = $request->input('arxiv_id')) {
            $query->where('arxiv_id', $arxivId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('abstract', 'like', '%' . $search . '%');
            });
        }

        $papers = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $papers->map(fn ($p) => $this->format($p)),
            'count' => $papers->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:1000',
            'authors'        => 'nullable|array|max:200',
            'authors.*'      => 'string|max:255',
            'abstract'       => 'nullable|string|max:50000',
            'url'            => 'nullable|url|max:2000',
            'pdf_url'        => 'nullable|url|max:2000',
            'arxiv_id'       => 'nullable|string|max:100',
            'doi'            => 'nullable|string|max:255',
            'venue'          => 'nullable|string|max:255',
            'published_date' => 'nullable|date',
            'status'         => 'nullable|in:' . implode(',', self::STATUSES),
            'notes'          => 'nullable|string|max:50000',
            'meta'           => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $paper = ResearchPaper::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'title'          => $data['title'],
            'authors'        => $data['authors'] ?? null,
            'abstract'       => $data['abstract'] ?? null,
            'url'            => $data['url'] ?? null,
            'pdf_url'        => $data['pdf_url'] ?? null,
            'arxiv_id'       => $data['arxiv_id'] ?? null,
            'doi'            => $data['doi'] ?? null,
            'venue'          => $data['venue'] ?? null,
            'published_date' => $data['published_date'] ?? null,
            'status'         => $data['status'] ?? 'to_read',
            'notes'          => $data['notes'] ?? null,
            'meta'           => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_research_paper', 'research_paper', $paper->id, 'low',
            "title={$paper->title}, status={$paper->status}", "id={$paper->id}");

        return response()->json(['data' => $this->format($paper)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $paper = ResearchPaper::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($paper)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'sometimes|string|max:1000',
            'authors'        => 'sometimes|nullable|array|max:200',
            'authors.*'      => 'string|max:255',
            'abstract'       => 'sometimes|nullable|string|max:50000',
            'url'            => 'sometimes|nullable|url|max:2000',
            'pdf_url'        => 'sometimes|nullable|url|max:2000',
            'arxiv_id'       => 'sometimes|nullable|string|max:100',
            'doi'            => 'sometimes|nullable|string|max:255',
            'venue'          => 'sometimes|nullable|string|max:255',
            'published_date' => 'sometimes|nullable|date',
            'status'         => 'sometimes|in:' . implode(',', self::STATUSES),
            'notes'          => 'sometimes|nullable|string|max:50000',
            'meta'           => 'sometimes|nullable|array',
        ]);

        $user  = $this->apiUser($request);
        $paper = ResearchPaper::where('user_id', $user->id)->findOrFail($id);

        foreach (['title', 'authors', 'abstract', 'url', 'pdf_url', 'arxiv_id', 'doi', 'venue', 'published_date', 'status', 'notes', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $paper->{$field} = $data[$field];
            }
        }

        $paper->save();

        $this->audit($request, 'update_research_paper', 'research_paper', $paper->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$paper->id}");

        return response()->json(['data' => $this->format($paper)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $paper = ResearchPaper::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $paper->delete();

        $this->audit($request, 'delete_research_paper', 'research_paper', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    public function format(ResearchPaper $p): array
    {
        return [
            'id'             => $p->id,
            'title'          => $p->title,
            'authors'        => $p->authors ?? [],
            'abstract'       => $p->abstract,
            'url'            => $p->url,
            'pdf_url'        => $p->pdf_url,
            'arxiv_id'       => $p->arxiv_id,
            'doi'            => $p->doi,
            'venue'          => $p->venue,
            'published_date' => $p->published_date?->toDateString(),
            'status'         => $p->status,
            'notes'          => $p->notes,
            'meta'           => $p->meta,
            'created_at'     => $p->created_at?->toISOString(),
            'updated_at'     => $p->updated_at?->toISOString(),
        ];
    }
}
