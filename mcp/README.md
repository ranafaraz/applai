# Personal CRM MCP Adapter

MCP server that exposes Personal Outreach CRM data as tools and resources for MCP-compatible AI clients (Claude Desktop, Cursor, etc.).

## Setup

```bash
cd mcp
cp .env.example .env
# Edit .env: fill in CRM_BASE_URL and CRM_API_KEY
npm install
npm run build
```

## Running

```bash
node dist/index.js
```

Or in dev mode:
```bash
npm run dev
```

## Claude Desktop config

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "personal-crm": {
      "command": "node",
      "args": ["/absolute/path/to/personal-crm/mcp/dist/index.js"],
      "env": {
        "CRM_BASE_URL": "https://your-crm.com/api/gpt/v1",
        "CRM_API_KEY": "pocrm_live_..."
      }
    }
  }
}
```

## Available Tools

| Tool | Description |
|------|-------------|
| `crm_dashboard_summary` | CRM stats and next actions |
| `crm_search_contacts` | Search contacts |
| `crm_get_contact` | Get contact by ID |
| `crm_search_opportunities` | Search opportunities |
| `crm_get_opportunity` | Get opportunity by ID |
| `crm_create_opportunity` | Create opportunity (deduplicates) |
| `crm_add_note` | Append note to contact or opportunity |
| `crm_create_email_draft` | Save draft for review (never sends) |
| `crm_create_followup` | Schedule reminder-only follow-up |
| `crm_recent_replies` | Get recent inbound replies |
| `crm_ingest_opportunities` | Bulk ingest from external sources |

## Available Resources

| URI | Description |
|-----|-------------|
| `crm://dashboard/summary` | Dashboard summary |
| `crm://opportunities/recent` | Recently updated opportunities |
| `crm://opportunities/due-soon` | Deadlines in next 7 days |
| `crm://contacts/recent` | Recent contacts |
| `crm://followups/due` | Due today or overdue |
| `crm://email-drafts/pending-review` | Drafts awaiting review |

## Security

- The MCP adapter calls the Laravel API — it never touches the database directly.
- API keys are stored in `.env` (never committed).
- Email drafts are **never sent automatically**.
- Suppressed contacts are blocked at the API layer.
