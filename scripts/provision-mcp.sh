#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Provision + (re)start the remote MCP Streamable-HTTP server on the VPS and
# wire nginx to proxy /mcp + the OAuth endpoints to it.
#
# Idempotent and safe to re-run. Backs up the nginx site file, validates with
# `nginx -t`, and auto-reverts a bad config so the live CRM is never taken down.
#
# Expects these env vars (provided by the deploy-mcp workflow):
#   IN_CLIENT_ID, IN_CLIENT_SECRET, IN_TOKEN_SECRET
# ---------------------------------------------------------------------------
set -e
APP=/var/www/crm.dexdevs.com
DOMAIN=crm.dexdevs.com
ENVF=/etc/crm-mcp.env
cd "$APP"

echo "=================== INSPECT ==================="
echo "node: $(command -v node || echo MISSING) $(node -v 2>/dev/null || true)"
echo "nginx: $(nginx -v 2>&1 || true)"
echo "listening 3000/80/443:"; ss -tlnp 2>/dev/null | grep -E ':3000|:80 |:443 ' || true
NGINX_SITE=$(grep -rls "server_name[^;]*${DOMAIN}" /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null | head -1)
echo "nginx site file: ${NGINX_SITE:-NONE FOUND}"

echo "=================== NODE INSTALL (if missing) ==================="
if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi
NODE_BIN=$(command -v node)
echo "node bin: $NODE_BIN ($($NODE_BIN -v))"

echo "=================== BUILD MCP ==================="
cd "$APP/mcp"
npm install --no-audit --no-fund
npm run build
test -f dist/http.js || { echo "BUILD FAILED: dist/http.js missing"; exit 1; }
cd "$APP"

echo "=================== ENV FILE (generate once, reuse) ==================="
if [ ! -f "$ENVF" ] || ! grep -q '^POCRM_API_KEY=..' "$ENVF"; then
  # Mint a dedicated full-scope MCP API token on prod for the adapter.
  cat > /tmp/mint_mcp_token.php <<'PHP'
<?php
chdir('/var/www/crm.dexdevs.com');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::where('email', 'ranafarazahmed@gmail.com')->first();
if (!$user) { fwrite(STDERR, "NO USER\n"); exit(1); }
$client = App\Models\ApiClient::where('source_type', 'mcp')->where('user_id', $user->id)->first();
if (!$client) {
    $client = App\Models\ApiClient::create([
        'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
        'name' => 'Applai MCP Connector', 'source_type' => 'mcp',
        'scopes' => App\Console\Commands\ExpandTokenCommand::FULL_SCOPES, 'is_active' => true,
    ]);
} else {
    $client->scopes = App\Console\Commands\ExpandTokenCommand::FULL_SCOPES;
    $client->is_active = true;
    $client->save();
}
['raw' => $raw, 'hash' => $hash, 'prefix' => $prefix] = App\Models\ApiClientToken::generateRaw('live');
App\Models\ApiClientToken::create([
    'api_client_id' => $client->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
    'name' => 'mcp-connector', 'token_hash' => $hash, 'token_prefix' => $prefix, 'is_active' => true,
]);
file_put_contents('/tmp/mcp_token.txt', $raw);
fwrite(STDERR, "minted mcp token for client {$client->id} (" . count($client->scopes) . " scopes)\n");
PHP
  php /tmp/mint_mcp_token.php
  MCP_TOKEN=$(cat /tmp/mcp_token.txt)
  rm -f /tmp/mint_mcp_token.php /tmp/mcp_token.txt
  cat > "$ENVF" <<EOF
POCRM_BASE_URL=https://crm.dexdevs.com/api/gpt/v1
POCRM_API_KEY=${MCP_TOKEN}
MCP_HTTP_PORT=3000
MCP_HTTP_HOST=127.0.0.1
MCP_HTTP_PATH=/mcp
MCP_EXTERNAL_URL=https://crm.dexdevs.com/mcp
MCP_OAUTH_ISSUER_URL=https://crm.dexdevs.com
MCP_OAUTH_CLIENT_ID=${IN_CLIENT_ID}
MCP_OAUTH_CLIENT_SECRET=${IN_CLIENT_SECRET}
MCP_OAUTH_TOKEN_SECRET=${IN_TOKEN_SECRET}
EOF
  chmod 600 "$ENVF"
  echo "wrote fresh $ENVF"
else
  # Keep the existing prod token; refresh only the OAuth/client values.
  sed -i "/^MCP_OAUTH_CLIENT_ID=/d;/^MCP_OAUTH_CLIENT_SECRET=/d;/^MCP_OAUTH_TOKEN_SECRET=/d;/^MCP_EXTERNAL_URL=/d;/^MCP_OAUTH_ISSUER_URL=/d" "$ENVF"
  {
    echo "MCP_EXTERNAL_URL=https://crm.dexdevs.com/mcp"
    echo "MCP_OAUTH_ISSUER_URL=https://crm.dexdevs.com"
    echo "MCP_OAUTH_CLIENT_ID=${IN_CLIENT_ID}"
    echo "MCP_OAUTH_CLIENT_SECRET=${IN_CLIENT_SECRET}"
    echo "MCP_OAUTH_TOKEN_SECRET=${IN_TOKEN_SECRET}"
  } >> "$ENVF"
  echo "updated OAuth values in existing $ENVF"
fi
echo "client_id in env: $(grep '^MCP_OAUTH_CLIENT_ID=' "$ENVF")"

echo "=================== SUPERVISOR crm-mcp ==================="
command -v supervisorctl >/dev/null 2>&1 || apt-get install -y supervisor >/dev/null 2>&1
mkdir -p /etc/supervisor/conf.d
# Build the supervisor environment= line from the env file (KEY="VALUE",KEY="VALUE").
ENV_INLINE=$(grep -v '^#' "$ENVF" | grep -v '^$' | sed -e 's/=/="/' -e 's/$/"/' | paste -sd, -)
cat > /etc/supervisor/conf.d/crm-mcp.conf <<EOF
[program:crm-mcp]
command=${NODE_BIN} /var/www/crm.dexdevs.com/mcp/dist/http.js
directory=/var/www/crm.dexdevs.com/mcp
autostart=true
autorestart=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/crm-mcp.log
stopwaitsecs=10
environment=${ENV_INLINE}
EOF
service supervisor start 2>/dev/null || true
supervisorctl reread 2>/dev/null || true
supervisorctl update 2>/dev/null || true
supervisorctl restart crm-mcp 2>/dev/null || supervisorctl start crm-mcp 2>/dev/null || true
sleep 2
echo "--- supervisor status ---"; supervisorctl status crm-mcp 2>/dev/null || true
echo "--- local health ---"
curl -s --max-time 8 http://127.0.0.1:3000/health || { echo "LOCAL HEALTH FAILED"; tail -40 /var/log/crm-mcp.log 2>/dev/null; }
echo

echo "=================== NGINX WIRING (safe) ==================="
mkdir -p /etc/nginx/snippets
cat > /etc/nginx/snippets/crm-mcp.conf <<'NG'
# MCP Streamable HTTP + OAuth — proxied to the Node adapter on loopback.
location = /mcp {
    proxy_pass http://127.0.0.1:3000/mcp;
    proxy_http_version 1.1;
    proxy_set_header Connection '';
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 3600s;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Authorization $http_authorization;
}
location ^~ /oauth/ {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Authorization $http_authorization;
}
location ^~ /.well-known/oauth-authorization-server {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
}
location ^~ /.well-known/oauth-protected-resource {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
}
location = /.well-known/openid-configuration {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
}
NG

if [ -z "$NGINX_SITE" ]; then
  echo "!! Could not locate the nginx server file for ${DOMAIN}."
  echo "!! MCP server is running on 127.0.0.1:3000 but is NOT yet public."
  echo "!! Add 'include /etc/nginx/snippets/crm-mcp.conf;' inside the 443 server block manually."
elif grep -q "crm-mcp.conf" "$NGINX_SITE"; then
  echo "include already present in $NGINX_SITE"
  nginx -t && systemctl reload nginx && echo "nginx reloaded"
else
  BACKUP="${NGINX_SITE}.bak.$(date +%s)"
  cp "$NGINX_SITE" "$BACKUP"
  echo "backed up $NGINX_SITE -> $BACKUP"
  # Insert the include immediately after the first ssl_certificate line, which
  # only exists inside the TLS (443) server block.
  if grep -q "ssl_certificate " "$NGINX_SITE"; then
    awk 'BEGIN{done=0} {print} /ssl_certificate / && !done {print "    include /etc/nginx/snippets/crm-mcp.conf;"; done=1}' "$BACKUP" > "$NGINX_SITE"
    if nginx -t 2>/tmp/nginx_test.out; then
      systemctl reload nginx
      echo "nginx config valid; reloaded with MCP include."
    else
      echo "!! nginx -t FAILED — reverting to backup. CRM site untouched."
      cat /tmp/nginx_test.out
      cp "$BACKUP" "$NGINX_SITE"
      nginx -t && systemctl reload nginx || true
    fi
  else
    echo "!! No ssl_certificate line found in $NGINX_SITE — not editing automatically."
    echo "!! Add 'include /etc/nginx/snippets/crm-mcp.conf;' to the 443 block manually."
  fi
fi

echo "=================== PUBLIC SELF-CHECK ==================="
echo "--- /health ---"; curl -s --max-time 10 https://crm.dexdevs.com/health || echo FAIL
echo; echo "--- /.well-known/oauth-authorization-server ---"
curl -s --max-time 10 https://crm.dexdevs.com/.well-known/oauth-authorization-server || echo FAIL
echo; echo "--- POST /mcp without auth (expect 401) ---"
curl -s -o /dev/null -w "%{http_code}\n" --max-time 10 -X POST https://crm.dexdevs.com/mcp \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' || echo FAIL
echo "=================== DONE ==================="
