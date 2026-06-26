# graphify
- **graphify** (`.claude/skills/graphify/SKILL.md`) - any input to knowledge graph. Trigger: `/graphify`
When the user types `/graphify`, invoke the Skill tool with `skill: "graphify"` before doing anything else.

# VPS SSH
Credentials are in `.env` (project root): `VPS_IP=162.0.211.214`, `VPS_SSH_Username=root`, `VPS_SSH_Password`. Use Python paramiko (`import paramiko`) — `sshpass` and `plink` are NOT available. Full deploy sequence in `CLAUDE.md` (project root) under "VPS / SSH".
