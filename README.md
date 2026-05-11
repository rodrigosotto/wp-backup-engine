# WP Snapshot Engine

> Professional version control for WordPress and Elementor — automatic incremental snapshots with a modern, Git-inspired visual timeline.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Admin UI](#admin-ui)
- [REST API](#rest-api)
- [Configuration](#configuration)
- [Snapshot Types](#snapshot-types)
- [File Structure](#file-structure)
- [FAQ](#faq)

---

## Features

- **Automatic snapshots** on every Elementor save, post update, and option change
- **Incremental deduplication** — snapshots are only stored when content actually changes (MD5 hashing)
- **Per-entity limit** — keeps the last 20 snapshots per post/option to avoid database bloat
- **Full or partial restore** — restore the full post state, or Elementor data only
- **Modern timeline UI** — grouped by day, filterable by type and date range
- **JSON payload viewer** — inspect the full raw snapshot directly in the browser
- **REST API** — headless access to all snapshot operations
- **No external dependencies** — pure vanilla JS, no jQuery, no npm build step required

---

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.1+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Elementor (optional) | 3.0+ |

---

## Installation

### Option A — Manual upload (recommended for most users)

1. Download or clone this repository.
2. Copy the `wp-snapshot-engine` folder into your site's plugin directory:
   ```
   wp-content/plugins/wp-snapshot-engine/
   ```
3. Log in to your WordPress admin dashboard.
4. Go to **Plugins → Installed Plugins**.
5. Find **WP Snapshot Engine** and click **Activate**.
6. The plugin automatically creates the `wp_snapshots` database table on activation.

### Option B — ZIP upload

1. Compress the `wp-snapshot-engine` folder into a `.zip` file.
2. In the WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the `.zip` file and click **Install Now**, then **Activate**.

### Verify installation

After activation, you should see a **Snapshots** item in the left sidebar of the WordPress admin with a backup icon.

---

## How It Works

### Automatic capture

The plugin listens to three WordPress hooks and creates a snapshot whenever content changes:

| Hook | Trigger | Snapshot type |
|---|---|---|
| `elementor/editor/after_save` | Saving in the Elementor editor | `elementor` |
| `save_post` | Publishing or updating any post/page | `post` |
| `updated_option` | Any WordPress option change | `option` |

### Deduplication

Before storing a snapshot, the plugin hashes the payload with MD5 and compares it to the previous hash for that entity. If the content has not changed, **no snapshot is written**. This prevents duplicate entries from rapid saves.

### Storage limit

Each entity (post or option) keeps a maximum of **20 snapshots**. When the limit is exceeded, the oldest snapshot is automatically deleted.

### Snapshot data

Each snapshot stores:

- **Post snapshots** — `post_title`, `post_content`, `post_status`, `post_type`, plus all Elementor meta keys (`_elementor_data`, `_elementor_page_settings`, etc.)
- **Option snapshots** — the option name and its new value

---

## Admin UI

Navigate to **Snapshots** in the WordPress admin sidebar.

### Timeline view

The main screen shows all snapshots in a vertical timeline, grouped by calendar day (newest first). Each entry displays:

- An icon indicating the snapshot type (🎨 Elementor / 📝 Post / ⚙️ Option)
- A descriptive title
- The exact timestamp
- The post ID (when applicable)

### Filtering

Use the filter bar at the top to narrow results by:

- **Type** — Elementor, Post update, or Option change
- **From / To date** — restrict to a specific date range

Click **Apply Filters** to reload the timeline, or **Reset** to clear all filters.

### Timeline item actions

Each snapshot card has four action buttons:

| Button | Action |
|---|---|
| **Details** | Opens the details panel with the full JSON payload |
| **Restore** | Restores the full snapshot (post content + all meta + Elementor data) |
| **Elementor only** | Restores only the Elementor meta, leaving `post_content` untouched |
| **Delete** | Permanently deletes this snapshot |

### Details panel

Clicking **Details** (or the card itself) opens a side panel with two tabs:

- **Payload** — the full snapshot JSON, syntax-highlighted and formatted
- **Diff vs Current** — a simple line-by-line view of the stored Elementor data

### Restoring a snapshot

1. Find the snapshot you want in the timeline.
2. Click **Restore** for a full restore, or **Elementor only** to restore only the page builder data.
3. Confirm the browser prompt.
4. A toast notification confirms success or shows an error.

> **Note:** Restoring overwrites the current post content. Elementor's cache is automatically cleared after an Elementor restore.

---

## REST API

All endpoints are under the namespace `wp-snapshot-engine/v1`. Every request requires a valid `X-WP-Nonce` header and an authenticated user with the `manage_options` capability.

### List snapshots

```
GET /wp-json/wp-snapshot-engine/v1/snapshots
```

**Query parameters:**

| Parameter | Type | Description |
|---|---|---|
| `page` | integer | Page number (default: `1`) |
| `per_page` | integer | Items per page, max 100 (default: `20`) |
| `snapshot_type` | string | Filter by type: `elementor`, `post`, `option` |
| `entity_type` | string | Filter by entity type |
| `entity_id` | integer | Filter by post ID |
| `date_from` | string | ISO date string, e.g. `2025-01-01` |
| `date_to` | string | ISO date string |

**Example response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "entity_type": "post",
      "entity_id": 5,
      "snapshot_type": "elementor",
      "hash": "d41d8cd98f00b204e9800998ecf8427e",
      "created_at": "2025-05-11 14:30:00",
      "group_id": "elementor_5_1715435400",
      "payload_preview": "{\"post\":{\"ID\":5,\"post_title\":\"Homepage\"..."
    }
  ],
  "meta": {
    "total": 84,
    "per_page": 20,
    "page": 1
  }
}
```

---

### Get a single snapshot

```
GET /wp-json/wp-snapshot-engine/v1/snapshots/{id}
```

Returns the full snapshot including the decoded `payload` object.

---

### Delete a snapshot

```
DELETE /wp-json/wp-snapshot-engine/v1/snapshots/{id}
```

---

### Restore a snapshot (full)

```
POST /wp-json/wp-snapshot-engine/v1/restore/{id}
```

Restores post content, post meta, and all Elementor data from the snapshot.

---

### Restore Elementor data only

```
POST /wp-json/wp-snapshot-engine/v1/restore/{id}/elementor
```

Restores only `_elementor_data` and related meta keys. The `post_content` field is left untouched.

---

### Example: restore via cURL

```bash
curl -X POST \
  https://yoursite.com/wp-json/wp-snapshot-engine/v1/restore/42 \
  -H "X-WP-Nonce: <your-nonce>"
```

---

## Configuration

There are no settings pages yet — the plugin works out of the box. Key defaults are defined as constants in the service class:

| Setting | Default | Where to change |
|---|---|---|
| Max snapshots per entity | `20` | `Snapshot_Service::MAX_PER_ENTITY` |
| Ignored option keys | `cron`, `_transient_*`, etc. | `Snapshot_Manager::IGNORED_OPTIONS` |
| Elementor meta keys captured | 6 keys | `Elementor_Serializer::ELEMENTOR_KEYS` |

To change the limit, edit `includes/class-snapshot-service.php`:

```php
private const MAX_PER_ENTITY = 30; // Change to your preferred limit
```

---

## Snapshot Types

| Icon | Type key | Triggered by |
|---|---|---|
| 🎨 | `elementor` | Saving in Elementor editor |
| 📝 | `post` | Classic editor / Gutenberg post save |
| ⚙️ | `option` | Any `update_option()` call |
| 🔌 | `plugin` | Reserved for future plugin/theme events |
| 🚀 | `system` | Reserved for future system-level events |

---

## File Structure

```
wp-snapshot-engine/
├── wp-snapshot-engine.php              # Main plugin file, autoloader, hook registration
├── includes/
│   ├── class-installer.php             # Database table creation (dbDelta)
│   ├── class-hasher.php                # MD5 payload hashing
│   ├── class-elementor-serializer.php  # Elementor meta capture and restore
│   ├── class-snapshot-repository.php   # Database layer (CRUD + pagination)
│   ├── class-snapshot-service.php      # Snapshot orchestration + dedup logic
│   ├── class-snapshot-manager.php      # WordPress hook listeners
│   ├── class-restore-service.php       # Full and partial restore logic
│   ├── class-rest-api.php              # WP REST API endpoints
│   └── class-admin.php                 # Admin menu + asset loading
├── views/
│   └── admin-page.php                  # Admin page HTML shell
└── assets/
    ├── css/admin.css                   # Timeline UI styles
    └── js/admin.js                     # Vanilla JS SPA (no build step)
```

---

## FAQ

**Does this plugin replace WordPress revisions?**
No. It works alongside the native revision system and focuses on capturing Elementor data, which is not stored in revisions.

**Will it work without Elementor?**
Yes. Post and option snapshots work on any WordPress installation. Elementor-specific features are activated only when Elementor is present.

**What happens when the 20-snapshot limit is reached?**
The oldest snapshot for that entity is automatically deleted when a new one is created. You can increase the limit by changing `MAX_PER_ENTITY` in `class-snapshot-service.php`.

**Can I restore a snapshot programmatically?**
Yes — use the REST API or instantiate `Restore_Service` directly and call `restore_full( $snapshot_id )` or `restore_elementor_only( $snapshot_id )`.

**Is the data encrypted?**
No. Snapshot payloads are stored as plain JSON in the database. Do not use this plugin as a security vault for sensitive option values.

**Will restoring a snapshot trigger another snapshot?**
Yes — restoring a post via `wp_update_post()` fires `save_post`, which may create a new snapshot of the restored state. This is intentional and gives you a full audit trail.

---

## License

GPL-2.0+. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
