<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function gptActions(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        $schema = [
            'openapi' => '3.1.0',
            'info' => [
                'title'       => 'Personal Outreach CRM – GPT Actions API',
                'version'     => '1.0.0',
                'description' => 'Allows a Custom GPT or MCP agent to search, create, and manage CRM data on behalf of the authenticated user. All actions require an X-Api-Key header. Email drafts are never sent automatically.',
            ],
            'servers' => [
                ['url' => $base . '/api/gpt/v1', 'description' => 'Production CRM API'],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'X-Api-Key',
                        'description' => 'API key generated in CRM → Settings → Integrations. Format: pocrm_live_<token>',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error'   => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'Opportunity' => [
                        'type' => 'object',
                        'properties' => [
                            'id'           => ['type' => 'integer'],
                            'title'        => ['type' => 'string'],
                            'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                            'organization' => ['type' => 'string'],
                            'status'       => ['type' => 'string'],
                            'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'deadline'     => ['type' => 'string', 'format' => 'date'],
                            'url'          => ['type' => 'string', 'format' => 'uri'],
                            'description'  => ['type' => 'string'],
                            'notes'        => ['type' => 'string'],
                            'created_at'   => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Contact' => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'integer'],
                            'full_name'   => ['type' => 'string'],
                            'first_name'  => ['type' => 'string'],
                            'last_name'   => ['type' => 'string'],
                            'email'       => ['type' => 'string', 'format' => 'email'],
                            'company'     => ['type' => 'string'],
                            'job_title'   => ['type' => 'string'],
                            'status'      => ['type' => 'string'],
                            'created_at'  => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'EmailDraft' => [
                        'type' => 'object',
                        'properties' => [
                            'id'             => ['type' => 'integer'],
                            'subject'        => ['type' => 'string'],
                            'to_email'       => ['type' => 'string', 'format' => 'email'],
                            'to_name'        => ['type' => 'string'],
                            'status'         => ['type' => 'string'],
                            'contact_id'     => ['type' => 'integer'],
                            'opportunity_id' => ['type' => 'integer', 'nullable' => true],
                            'preview'        => ['type' => 'string'],
                            'created_at'     => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'FollowUp' => [
                        'type' => 'object',
                        'properties' => [
                            'id'             => ['type' => 'integer'],
                            'contact_id'     => ['type' => 'integer'],
                            'opportunity_id' => ['type' => 'integer', 'nullable' => true],
                            'due_at'         => ['type' => 'string', 'format' => 'date-time'],
                            'status'         => ['type' => 'string'],
                            'subject'        => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'DashboardSummary' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => [
                                'type' => 'object',
                                'properties' => [
                                    'total_opportunities'    => ['type' => 'integer'],
                                    'active_opportunities'   => ['type' => 'integer'],
                                    'follow_ups_due_today'   => ['type' => 'integer'],
                                    'replies_needing_review' => ['type' => 'integer'],
                                    'deadline_soon'          => ['type' => 'integer'],
                                ],
                            ],
                            'next_actions' => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [['ApiKeyAuth' => []]],
            'paths' => [
                '/health' => [
                    'get' => [
                        'operationId' => 'getHealth',
                        'summary'     => 'API health check',
                        'description' => 'Returns 200 if the API is reachable. No scope required.',
                        'security'    => [],
                        'responses'   => [
                            '200' => ['description' => 'Healthy'],
                        ],
                    ],
                ],
                '/me' => [
                    'get' => [
                        'operationId' => 'getMe',
                        'summary'     => 'Get authenticated client identity',
                        'description' => 'Returns the user and API client details for the provided key.',
                        'responses'   => [
                            '200' => ['description' => 'Identity info'],
                            '401' => ['description' => 'Unauthenticated'],
                        ],
                    ],
                ],
                '/dashboard-summary' => [
                    'get' => [
                        'operationId' => 'getDashboardSummary',
                        'summary'     => 'CRM dashboard summary',
                        'description' => 'Returns open opportunity counts, follow-ups due today, pending replies, and suggested next actions. Requires scope: dashboard:read.',
                        'responses' => [
                            '200' => [
                                'description' => 'Dashboard summary',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/DashboardSummary'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/opportunities' => [
                    'get' => [
                        'operationId' => 'searchOpportunities',
                        'summary'     => 'Search CRM opportunities',
                        'description' => 'Search and filter opportunities by keyword, type, status, priority, or deadline. Requires scope: opportunities:read.',
                        'parameters'  => [
                            ['name' => 'q',               'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'Search text'],
                            ['name' => 'type',            'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'job, scholarship, research, grant, or networking'],
                            ['name' => 'status',          'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'open, active, applied, closed, etc.'],
                            ['name' => 'priority',        'in' => 'query', 'schema' => ['type' => 'string'],  'description' => 'low, medium, or high'],
                            ['name' => 'deadline_before', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'deadline_after',  'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'limit',           'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'List of matching opportunities'],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'createOpportunity',
                        'summary'     => 'Create a new opportunity',
                        'description' => 'Creates an opportunity in the CRM. Deduplicates by title + organization + URL. Requires scope: opportunities:write.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'     => 'object',
                                        'required' => ['title', 'type', 'organization'],
                                        'properties' => [
                                            'title'        => ['type' => 'string'],
                                            'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                                            'organization' => ['type' => 'string'],
                                            'description'  => ['type' => 'string'],
                                            'url'          => ['type' => 'string', 'format' => 'uri'],
                                            'status'       => ['type' => 'string'],
                                            'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                                            'deadline'     => ['type' => 'string', 'format' => 'date'],
                                            'notes'        => ['type' => 'string'],
                                        ],
                                    ],
                                    'example' => [
                                        'title'        => 'Research Scientist – NLP',
                                        'type'         => 'job',
                                        'organization' => 'DeepMind',
                                        'url'          => 'https://deepmind.com/careers/123',
                                        'priority'     => 'high',
                                        'deadline'     => '2026-08-01',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Opportunity created'],
                            '200' => ['description' => 'Duplicate – existing opportunity returned'],
                        ],
                    ],
                ],
                '/opportunities/{id}' => [
                    'get' => [
                        'operationId' => 'getOpportunity',
                        'summary'     => 'Get opportunity by ID',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Opportunity detail']],
                    ],
                ],
                '/opportunities/{id}/notes' => [
                    'post' => [
                        'operationId' => 'addOpportunityNote',
                        'summary'     => 'Append a note to an opportunity',
                        'description' => 'Appends an AI-generated note to the opportunity notes field. Requires scope: notes:write.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['note'], 'properties' => ['note' => ['type' => 'string']]]]],
                        ],
                        'responses' => ['200' => ['description' => 'Note added']],
                    ],
                ],
                '/contacts' => [
                    'get' => [
                        'operationId' => 'searchContacts',
                        'summary'     => 'Search CRM contacts',
                        'description' => 'Search contacts by name, email, or company. Requires scope: contacts:read.',
                        'parameters'  => [
                            ['name' => 'q',            'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'email',        'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'email']],
                            ['name' => 'organization', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status',       'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'limit',        'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ],
                        'responses' => ['200' => ['description' => 'List of contacts']],
                    ],
                    'post' => [
                        'operationId' => 'createContact',
                        'summary'     => 'Create a new contact',
                        'description' => 'Creates a contact in the CRM. Deduplicates by email. Requires scope: contacts:write.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'first_name'   => ['type' => 'string'],
                                            'full_name'    => ['type' => 'string', 'description' => 'Alternative to first_name + last_name'],
                                            'last_name'    => ['type' => 'string'],
                                            'email'        => ['type' => 'string', 'format' => 'email'],
                                            'phone'        => ['type' => 'string'],
                                            'company'      => ['type' => 'string'],
                                            'job_title'    => ['type' => 'string'],
                                            'linkedin_url' => ['type' => 'string', 'format' => 'uri'],
                                            'notes'        => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Contact created'],
                            '200' => ['description' => 'Duplicate – existing contact returned'],
                        ],
                    ],
                ],
                '/contacts/{id}' => [
                    'get' => [
                        'operationId' => 'getContact',
                        'summary'     => 'Get contact by ID',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses'   => ['200' => ['description' => 'Contact detail']],
                    ],
                ],
                '/contacts/{id}/notes' => [
                    'post' => [
                        'operationId' => 'addContactNote',
                        'summary'     => 'Append a note to a contact',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['note'], 'properties' => ['note' => ['type' => 'string']]]]],
                        ],
                        'responses' => ['200' => ['description' => 'Note added']],
                    ],
                ],
                '/email-drafts' => [
                    'get' => [
                        'operationId' => 'listEmailDrafts',
                        'summary'     => 'List pending email drafts',
                        'description' => 'Returns drafts awaiting user review. Requires scope: drafts:read.',
                        'responses'   => ['200' => ['description' => 'List of drafts']],
                    ],
                    'post' => [
                        'operationId' => 'createEmailDraft',
                        'summary'     => 'Create an email draft for review',
                        'description' => 'Saves a draft email linked to a contact and (optionally) an opportunity. The draft is NEVER sent automatically. User must review and send from the CRM. Requires scope: drafts:create.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'     => 'object',
                                        'required' => ['contact_id', 'subject', 'body'],
                                        'properties' => [
                                            'contact_id'     => ['type' => 'integer'],
                                            'opportunity_id' => ['type' => 'integer', 'nullable' => true],
                                            'subject'        => ['type' => 'string'],
                                            'body'           => ['type' => 'string'],
                                            'draft_type'     => ['type' => 'string', 'enum' => ['initial_outreach', 'follow_up', 'thank_you', 'general']],
                                            'tone'           => ['type' => 'string', 'enum' => ['professional', 'casual', 'formal']],
                                        ],
                                    ],
                                    'example' => [
                                        'contact_id'     => 42,
                                        'opportunity_id' => 7,
                                        'subject'        => 'Research collaboration inquiry',
                                        'body'           => 'Dear Prof. Smith,\n\nI am writing to explore...',
                                        'draft_type'     => 'initial_outreach',
                                        'tone'           => 'professional',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Draft saved – awaiting user review'],
                            '422' => ['description' => 'Suppressed or blocked contact'],
                        ],
                    ],
                ],
                '/follow-ups' => [
                    'post' => [
                        'operationId' => 'createFollowUp',
                        'summary'     => 'Schedule a follow-up reminder',
                        'description' => 'Creates a reminder-only follow-up. Auto-sending is disabled. Requires scope: followups:create.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'     => 'object',
                                        'required' => ['contact_id', 'due_at'],
                                        'properties' => [
                                            'contact_id'        => ['type' => 'integer'],
                                            'opportunity_id'    => ['type' => 'integer', 'nullable' => true],
                                            'due_at'            => ['type' => 'string', 'format' => 'date-time'],
                                            'notes'             => ['type' => 'string'],
                                            'suggested_subject' => ['type' => 'string'],
                                            'suggested_body'    => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Follow-up scheduled'],
                            '422' => ['description' => 'Blocked – suppressed contact'],
                        ],
                    ],
                ],
                '/follow-ups/due' => [
                    'get' => [
                        'operationId' => 'getDueFollowUps',
                        'summary'     => 'List follow-ups due today or overdue',
                        'description' => 'Returns pending follow-ups due today or in the past. Requires scope: followups:read.',
                        'responses'   => ['200' => ['description' => 'Due follow-ups']],
                    ],
                ],
                '/replies/recent' => [
                    'get' => [
                        'operationId' => 'getRecentReplies',
                        'summary'     => 'Get recent inbound replies',
                        'description' => 'Returns matched inbound replies. Requires scope: replies:read.',
                        'parameters'  => [
                            ['name' => 'since',          'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                            ['name' => 'opportunity_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'contact_id',     'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'sentiment',      'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']]],
                            ['name' => 'limit',          'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 50]],
                        ],
                        'responses' => ['200' => ['description' => 'Recent replies']],
                    ],
                ],
                '/ingestion/opportunities' => [
                    'post' => [
                        'operationId' => 'ingestOpportunities',
                        'summary'     => 'Bulk ingest opportunities from external sources',
                        'description' => 'Accepts up to 50 opportunities per call. Deduplicates automatically. Intended for n8n, scrapers, and automation agents. Requires scope: opportunities:write.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'required'   => ['items'],
                                        'properties' => [
                                            'items' => [
                                                'type'     => 'array',
                                                'maxItems' => 50,
                                                'items'    => [
                                                    'type'     => 'object',
                                                    'required' => ['title', 'type', 'organization'],
                                                    'properties' => [
                                                        'title'        => ['type' => 'string', 'maxLength' => 255],
                                                        'type'         => ['type' => 'string', 'enum' => ['job', 'scholarship', 'research', 'grant', 'networking']],
                                                        'organization' => ['type' => 'string', 'maxLength' => 255],
                                                        'description'  => ['type' => 'string'],
                                                        'url'          => ['type' => 'string', 'format' => 'uri'],
                                                        'deadline'     => ['type' => 'string', 'format' => 'date'],
                                                        'priority'     => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                                                        'notes'        => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Ingestion result with created/duplicate counts']],
                    ],
                ],
                '/ingestion/contacts' => [
                    'post' => [
                        'operationId' => 'ingestContacts',
                        'summary'     => 'Bulk ingest contacts from external sources',
                        'description' => 'Accepts up to 50 contacts per call. Deduplicates by email. Requires scope: contacts:write.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'       => 'object',
                                        'required'   => ['items'],
                                        'properties' => [
                                            'items' => [
                                                'type'     => 'array',
                                                'maxItems' => 50,
                                                'items'    => [
                                                    'type'     => 'object',
                                                    'required' => ['first_name'],
                                                    'properties' => [
                                                        'first_name'   => ['type' => 'string', 'maxLength' => 100],
                                                        'last_name'    => ['type' => 'string', 'maxLength' => 100],
                                                        'email'        => ['type' => 'string', 'format' => 'email'],
                                                        'company'      => ['type' => 'string', 'maxLength' => 255],
                                                        'job_title'    => ['type' => 'string', 'maxLength' => 255],
                                                        'phone'        => ['type' => 'string', 'maxLength' => 50],
                                                        'linkedin_url' => ['type' => 'string', 'format' => 'uri'],
                                                        'notes'        => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Ingestion result with created/duplicate counts']],
                    ],
                ],
                '/confirmations' => [
                    'post' => [
                        'operationId' => 'createConfirmation',
                        'summary'     => 'Request user confirmation for a high-risk action',
                        'description' => 'Creates a pending confirmation that the CRM user must approve or reject. Use this before any action that could have irreversible side-effects.',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type'     => 'object',
                                        'required' => ['action', 'description'],
                                        'properties' => [
                                            'action'      => ['type' => 'string'],
                                            'description' => ['type' => 'string'],
                                            'payload'     => ['type' => 'object'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Confirmation created']],
                    ],
                ],
                '/confirmations/{id}' => [
                    'get' => [
                        'operationId' => 'getConfirmation',
                        'summary'     => 'Poll confirmation status',
                        'description' => 'Returns the current status (pending, approved, rejected) of a confirmation request.',
                        'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                        'responses'   => ['200' => ['description' => 'Confirmation status'], '404' => ['description' => 'Not found or expired']],
                    ],
                ],
            ],
        ];

        return response()->json($schema)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json; charset=utf-8');
    }
}
