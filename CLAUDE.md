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
