## VPS / SSH

SSH credentials are in `.env` at the project root. Use Python paramiko (available) since `sshpass` is not installed:

```python
import paramiko
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('162.0.211.214', port=22, username='root', password='<VPS_SSH_Password from .env>', timeout=30)
stdin, stdout, stderr = client.exec_command("YOUR_COMMAND", timeout=120)
print(stdout.read().decode())
client.close()
```

Key `.env` vars: `VPS_IP`, `VPS_SSH_Port`, `VPS_SSH_Username`, `VPS_SSH_Password`.

### MCP deploy sequence (after committing to main)

```bash
cd /var/www/crm.dexdevs.com && git pull origin main
cd mcp && npm install && npm run build   # full install — NOT --omit=dev (TypeScript is a devDep)
supervisorctl restart crm-mcp && supervisorctl status crm-mcp
```

Supervisor process name: `crm-mcp`. Web root: `/var/www/crm.dexdevs.com`.

---

## Serena MCP

Serena provides LSP-backed code intelligence (symbol search, find references, diagnostics, rename, etc.) via 21 tools.

### Session start

If this is the first time working in this project in the session, or the user seems unfamiliar with the codebase state, call `initial_instructions` to get Serena's onboarding context for this project. Call `onboarding` only if the user explicitly asks for a guided tour or if `initial_instructions` indicates it's needed.

Do NOT call either tool on every session start automatically — only when orientation is actually useful for the current task.

---

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

### Session start (MANDATORY)

At the start of every new session, run this to sync the graph with any code written since last time:

```powershell
$env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("PATH","User")
& (Get-Content graphify-out\.graphify_python) -m graphify update .
```

**This only updates the graph on disk. Do NOT read, cat, or load graph.json, GRAPH_REPORT.md, or any graphify-out/ file into context.** The update is AST-only (~10-20s, no API cost). Confirm it completed, then wait for the user's task.

### Querying — task-scoped only, never full graph

Once you understand what the user needs, fetch only the nodes relevant to that task:

- `graphify query "<question>"` — scoped subgraph for the current task
- `graphify path "<A>" "<B>"` — relationship between two specific concepts
- `graphify explain "<concept>"` — focused explanation of one node

**Never load the full graph into context.** Do not read `graphify-out/GRAPH_REPORT.md` unless the user explicitly asks for an architecture review. Do not read `graphify-out/graph.json` at all.

### Raw file reads

Only read raw source files after graphify has oriented you to the relevant nodes, or when you need to edit/debug specific lines. Do not browse source files to understand architecture — use graphify queries instead.

### After modifying code

Re-run the session-start update command after significant changes so the graph stays current for the rest of the session.
