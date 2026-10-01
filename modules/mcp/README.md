# MCP server and REST API (`mcp` module set)

Lets AI assistants and scripts work with the mail in Cypht: read, search, organize,
write drafts, send, schedule, and manage folders, tags and contacts.

- Clients can use the [Model Context Protocol](https://modelcontextprotocol.io) endpoint at
  `/mcp`. Supported clients include ChatGPT, Claude Code, Codex, Cursor and VS Code.
- Scripts can use the REST API at `/api/v1`, described by an OpenAPI document. MCP and REST are
  built from the same catalog of operations, so they offer the same features with the same
  checks.

The module set is off unless it is added to `CYPHT_MODULES`. Each user then turns access on
for their own account in **Settings, API and MCP**, and chooses what connections may do.

## Setup

1. Add `mcp` to `CYPHT_MODULES`.
2. Set `MCP_PUBLIC_URL` to the public HTTPS address of Cypht, for example
   `https://mail.example.com`. Clients use this to build every URL, and it is also the OAuth
   issuer. Plain HTTP is only accepted for `localhost`.
3. Create the tables with `php scripts/setup_database.php` (the Docker image does this when it
   starts). The module needs the Cypht database (`DB_*` settings), even when sessions and user
   settings are stored in files.
4. Route `/mcp`, `/oauth/*`, `/api/v1` and `/.well-known/oauth-*` to `index.php`.
   - Apache: `.htaccess` already does this, and also passes the `Authorization` header to PHP.
   - nginx: the Docker image works as is. Elsewhere, use a `try_files ... /index.php` fallback
     and do not deny `/.well-known/`.

When Cypht runs behind a reverse proxy:

- The proxy must keep the `Host` header, or the host it sends must be listed in
  `MCP_ALLOWED_HOSTS`. Requests with other hosts are refused.
- The proxy must pass the `Authorization` header.
- Rate limits use the connection address. To use the real client address instead, set
  `MCP_CLIENT_IP_HEADER`, for example to `CF-Connecting-IP` or `X-Forwarded-For`. Only do this
  when clients cannot reach Cypht without going through that proxy.
- Some paths are called by servers, not browsers: `/mcp`, `/.well-known/oauth-*`,
  `/oauth/register`, `/oauth/token` and `/oauth/revoke`. If an authentication proxy protects
  the site, these paths must bypass it. `/oauth/authorize` is opened in the user's browser and
  can stay behind it.

## How users connect

In **Settings, API and MCP**, the user:

- turns access on for their account;
- chooses the permissions and accounts allowed;
- manages connections, tokens and the activity log.

The permissions chosen there are the limit for every connection. Each connection either
follows them or narrows them further.

| Client | How it connects |
|---|---|
| ChatGPT | Developer mode. Add the server URL `https://mail.example.com/mcp` and choose OAuth. |
| Claude Code, Codex, Cursor, VS Code, scripts | OAuth, or a personal access token sent as `Authorization: Bearer ...`. The settings page shows ready to copy commands. |
| Clients that only support stdio | `npx mcp-remote https://mail.example.com/mcp` |
| REST API | A personal access token. The OpenAPI document is at `/api/v1/openapi.json`. |

The OAuth server works for public clients:

- It follows OAuth 2.1 with PKCE `S256` and returns the issuer (`iss`, RFC 9207) on every
  authorization response.
- Tokens are bound to the `/mcp` resource (RFC 8707).
- Clients register in one of two ways:
  - **Client ID Metadata Documents**, from the hosts in `MCP_CIMD_ALLOWED_HOSTS`. By default
    that is ChatGPT's `https://chatgpt.com/oauth/client.json`.
  - **Dynamic Client Registration** (RFC 7591), for other clients.
- On the authorization page the user signs in with their Cypht username and password, plus a
  two factor code when the 2fa module set is on. They then choose the permissions and accounts
  for the connection.

## Operations

| Permission | Default | Operations |
|---|---|---|
| Read messages and attachments | on | `get_profile`, `list_accounts`, `list_folders`, `list_messages`, `search_messages`, `get_message`, `get_thread`, `get_attachment`, `search`, `fetch`, `search_contacts`, `list_tags`, `list_scheduled` |
| Organize messages | on | `update_messages` (read, flagged), `move_messages`, `archive_messages`, `mark_junk`, `snooze_messages` |
| Write drafts | on | `create_draft` (new, reply, reply all, forward), `update_draft`, `delete_draft` |
| Send and schedule messages | off | `send_message`, `send_draft`, `manage_scheduled` |
| Move messages to the trash | off | `trash_messages`, `restore_messages` |
| Delete messages permanently | off | `delete_messages_permanently`, `empty_folder` |
| Manage folders | off | `create_folder`, `rename_folder`, `delete_folder` (a folder with messages also needs the permanent delete permission) |
| Manage tags | off | `tag_messages` |
| Edit contacts | off | `save_contact` |

Every operation is available as a REST endpoint (see the OpenAPI document) and as
`POST /api/v1/tools/{name}`.

Notes on how the operations behave:

- **Reading:** opens folders read only, so it never marks messages as read. HTML is converted
  to text, and hidden text and invisible characters are removed. Results label message content
  as untrusted.
- **Attachments:** text, HTML, CSV, JSON, XML, calendar files and attached messages are
  returned as text. Every attachment also gets a temporary download link, valid for
  `MCP_FILE_LINK_TTL` seconds.
- **Drafts:** written in plain text or Markdown, and reopen in the Cypht compose page. Files
  can come from ChatGPT (`openai/fileParams`) or as base64. Linked files are only downloaded
  over HTTPS from `MCP_UPLOAD_ALLOWED_HOSTS`, from public addresses, up to
  `MCP_MAX_UPLOAD_BYTES`.
- **Deleting:** only removes the requested messages. On servers without UIDPLUS, the request is
  refused if other messages are already marked as deleted. On Gmail, messages go through the
  trash.
- **Sending:** uses the SMTP server of the sending profile, saves a copy in the sent folder
  (except on Gmail, which saves its own) and follows the "Always BCC sending address" setting.
  It is limited by `MCP_SEND_PER_HOUR` and `MCP_SEND_PER_DAY`.

## Scheduled sends

Scheduled messages use the Cypht format, so Cypht lists and sends them too. Cypht only sends
them while it is open in a browser. To send them on time even when it is closed:

1. Create a runner token in **Settings, API and MCP, Scheduled sends**.
2. Call the runner endpoint every minute. With cron:

```
* * * * * curl -fsS -X POST -H "Authorization: Bearer $RUNNER_TOKEN" https://mail.example.com/api/v1/scheduled/run >/dev/null
```

The runner makes sure each message is sent only once, even with several runners. It waits
`MCP_SCHEDULED_GRACE` seconds after the scheduled time, so that a browser that has Cypht open
can send the message first. A runner token can only call this endpoint.

## Security

- **Tokens:** stored as SHA-256 hashes only. The user's password is needed to decrypt their
  settings, so it is sealed with a key that exists only in a form wrapped by that connection's
  tokens. Changing the Cypht password turns existing connections off until they are authorized
  again.
- **OAuth:**
  - authorization codes are single use;
  - refresh tokens rotate, and an old refresh token that is used again ends the connection;
  - sign in attempts and client registrations are rate limited;
  - the authorization pages have no scripts, use a strict Content Security Policy and refuse
    framing and cross-site posts.
- **Activity log:** records what each connection did, with counts, accounts and recipient
  domains. It never records message content or addresses.
- **Permissions:** changes apply to the next request. Tools for disabled permissions are not
  listed, and calls to them are refused and logged.

## Configuration

| Setting | Default | Purpose |
|---|---|---|
| `MCP_PUBLIC_URL` | (empty) | Public base URL; the endpoints answer 503 until it is set |
| `MCP_ALLOWED_HOSTS` | (empty) | Extra accepted `Host` headers, comma separated |
| `MCP_ACCESS_TOKEN_TTL` | `3600` | OAuth access token lifetime, seconds |
| `MCP_REFRESH_TOKEN_TTL` | `2592000` | OAuth refresh token lifetime, seconds |
| `MCP_FILE_LINK_TTL` | `900` | Attachment download link lifetime, seconds |
| `MCP_ACTIVITY_RETENTION_DAYS` | `90` | Days of activity kept (at most 10000 entries per user) |
| `MCP_CIMD_ALLOWED_HOSTS` | `chatgpt.com` | Hosts allowed to publish client metadata documents; empty turns CIMD off |
| `MCP_UPLOAD_ALLOWED_HOSTS` | ChatGPT file hosts | Hosts files can be downloaded from, `*.` matches subdomains |
| `MCP_MAX_UPLOAD_BYTES` | `26214400` | Largest total size of the attachments of one message |
| `MCP_SEND_PER_HOUR` | `30` | Messages sent per connection and hour |
| `MCP_SEND_PER_DAY` | `200` | Messages sent per user and day |
| `MCP_SCHEDULED_GRACE` | `120` | Seconds the runner waits after the scheduled time |
| `MCP_CLIENT_IP_HEADER` | (empty) | Trusted proxy header with the client address |

## Limitations

- Changes to folders, flags and messages need IMAP accounts.
- Tags and contacts are saved right away in the user settings. If a browser session that has
  unsaved settings changes saves them later, it can overwrite what an API call changed.
- On Gmail, folders are labels: deleting a folder removes the label, and the messages stay in
  All Mail.

## Tests

```
cd tests/phpunit && ./run.sh --testsuite modules_mcp
```
