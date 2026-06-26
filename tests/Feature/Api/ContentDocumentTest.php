<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private string $rawToken;
    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        [$this->user, $this->rawToken] = $this->makeApiToken(
            ['documents:read', 'documents:write', 'opportunities:read']
        );

        $this->opportunity = Opportunity::create([
            'user_id'   => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'title'     => 'Content Doc Opportunity',
            'status'    => 'active',
        ]);
    }

    private function header(): array
    {
        return ['X-Api-Key' => $this->rawToken];
    }

    private function createDoc(string $body, string $format = 'markdown', ?string $name = null): int
    {
        $response = $this->withHeaders($this->header())->postJson('/api/gpt/v1/documents', [
            'name'           => $name ?? 'Submission Package',
            'content_body'   => $body,
            'content_format' => $format,
            'opportunity_id' => $this->opportunity->id,
        ]);

        $response->assertCreated();

        return $response->json('data.document_id');
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function test_content_doc_creates_document_link_and_lists_under_opportunity(): void
    {
        $response = $this->withHeaders($this->header())->postJson('/api/gpt/v1/documents', [
            'name'           => 'Project Report',
            'content_body'   => "# Heading\n\nSome **bold** content.",
            'content_format' => 'markdown',
            'opportunity_id' => $this->opportunity->id,
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.name', 'Project Report')
                 ->assertJsonPath('data.is_content_doc', true)
                 ->assertJsonPath('data.current_version.content_format', 'markdown');

        $docId = $response->json('data.document_id');

        $this->assertDatabaseHas('api_documents', ['id' => $docId, 'is_content_doc' => true]);
        $this->assertDatabaseHas('api_document_links', [
            'api_document_id' => $docId,
            'entity_type'     => 'opportunity',
            'entity_id'       => $this->opportunity->id,
        ]);
        $this->assertDatabaseHas('api_document_versions', [
            'api_document_id' => $docId,
            'upload_source'   => 'inline_content',
            'content_format'  => 'markdown',
        ]);

        // Appears in the opportunity's document list.
        $list = $this->withHeaders($this->header())
            ->getJson("/api/gpt/v1/opportunities/{$this->opportunity->id}/documents");

        $list->assertOk()
             ->assertJsonFragment(['document_id' => $docId, 'is_content_doc' => true]);
    }

    public function test_read_content_returns_stored_body(): void
    {
        $docId = $this->createDoc("# Title\n\nbody text", 'markdown');

        $this->withHeaders($this->header())
            ->getJson("/api/gpt/v1/documents/{$docId}/content")
            ->assertOk()
            ->assertJsonPath('content_format', 'markdown')
            ->assertJsonPath('content_body', "# Title\n\nbody text");
    }

    // -------------------------------------------------------------------------
    // Export round-trip
    // -------------------------------------------------------------------------

    public function test_export_html_renders_markdown(): void
    {
        $docId = $this->createDoc("# Big Title\n\nParagraph here.", 'markdown');

        $response = $this->withHeaders($this->header())
            ->get("/api/gpt/v1/documents/{$docId}/export?format=html");

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('content-type'));
        $this->assertStringContainsString('<h1>Big Title</h1>', $response->getContent());
    }

    public function test_export_md_and_txt(): void
    {
        $docId = $this->createDoc("# Title\n\nHello world.", 'markdown');

        $md = $this->withHeaders($this->header())->get("/api/gpt/v1/documents/{$docId}/export?format=md");
        $md->assertOk();
        $this->assertStringContainsString('text/markdown', $md->headers->get('content-type'));
        $this->assertStringContainsString('# Title', $md->getContent());

        $txt = $this->withHeaders($this->header())->get("/api/gpt/v1/documents/{$docId}/export?format=txt");
        $txt->assertOk();
        $this->assertStringContainsString('text/plain', $txt->headers->get('content-type'));
        $this->assertStringContainsString('Hello world', $txt->getContent());
        $this->assertStringNotContainsString('<h1', $txt->getContent());
    }

    public function test_export_pdf_returns_pdf_bytes(): void
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('dompdf not installed in this environment.');
        }

        $docId = $this->createDoc("# PDF Title\n\nContent.", 'markdown');

        $response = $this->withHeaders($this->header())
            ->get("/api/gpt/v1/documents/{$docId}/export?format=pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(100, strlen($response->getContent()));
    }

    public function test_export_docx_returns_word_mime(): void
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            $this->markTestSkipped('PhpWord not installed in this environment.');
        }

        $docId = $this->createDoc("# DOCX Title\n\nContent.", 'markdown');

        $response = $this->withHeaders($this->header())
            ->get("/api/gpt/v1/documents/{$docId}/export?format=docx");

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('content-type')
        );
        // .docx is a zip archive → starts with the PK signature.
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_export_csv_with_table_and_without(): void
    {
        $table = "| Name | Score |\n| --- | --- |\n| Alice | 9 |\n| Bob | 7 |";
        $tableDoc = $this->createDoc($table, 'markdown', 'Scores');

        $csv = $this->withHeaders($this->header())
            ->get("/api/gpt/v1/documents/{$tableDoc}/export?format=csv");

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString('Alice', $csv->getContent());
        $this->assertStringContainsString('Bob', $csv->getContent());

        $plainDoc = $this->createDoc("# No tables here\n\nJust prose.", 'markdown', 'Prose');
        $this->withHeaders($this->header())
            ->get("/api/gpt/v1/documents/{$plainDoc}/export?format=csv")
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Versioning
    // -------------------------------------------------------------------------

    public function test_update_content_creates_new_version_and_flips_current(): void
    {
        $docId = $this->createDoc("v1 body", 'markdown');

        $update = $this->withHeaders($this->header())->postJson("/api/gpt/v1/documents/{$docId}/versions", [
            'content_body'   => "v2 body updated",
            'content_format' => 'markdown',
        ]);

        $update->assertCreated()->assertJsonPath('data.version_number', 2);

        // Current version flipped to v2.
        $this->withHeaders($this->header())->getJson("/api/gpt/v1/documents/{$docId}")
             ->assertOk()
             ->assertJsonPath('data.current_version.version_number', 2);

        // History intact: both versions present.
        $versions = $this->withHeaders($this->header())->getJson("/api/gpt/v1/documents/{$docId}/versions");
        $versions->assertOk()->assertJsonPath('count', 2);

        // Latest content reads back v2.
        $this->withHeaders($this->header())->getJson("/api/gpt/v1/documents/{$docId}/content")
             ->assertJsonPath('content_body', 'v2 body updated');
    }

    // -------------------------------------------------------------------------
    // UI render
    // -------------------------------------------------------------------------

    public function test_opportunity_show_page_lists_content_doc_with_download_links(): void
    {
        $docId = $this->createDoc("# Visible Report\n\nPreview text here.", 'markdown', 'Visible Report');

        $this->actingAs($this->user)
            ->get("/opportunities/{$this->opportunity->id}")
            ->assertOk()
            ->assertSee('Visible Report')
            ->assertSee('Content') // the content-doc badge
            ->assertSee(route('documents.api.export', ['id' => $docId, 'format' => 'pdf']), false)
            ->assertSee(route('documents.api.export', ['id' => $docId, 'format' => 'docx']), false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeApiToken(array $scopes): array
    {
        $tenant = Tenant::create([
            'name'   => 'Content Doc Tenant',
            'slug'   => 'content-doc-' . Str::random(6),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);

        $client = ApiClient::create([
            'user_id'     => $user->id,
            'name'        => 'Test Client',
            'source_type' => 'custom_gpt',
            'scopes'      => $scopes,
            'is_active'   => true,
        ]);

        $raw = 'pocrm_test_' . Str::random(40);

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $user->id,
            'name'          => 'Test Token',
            'token_hash'    => hash('sha256', $raw),
            'token_prefix'  => substr($raw, 0, 16),
            'is_active'     => true,
            'expires_at'    => now()->addYear(),
        ]);

        return [$user, $raw];
    }
}
