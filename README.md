# RestFullPlayerApi

A Laravel 9 REST API for managing football/soccer player rosters and their skill ratings, plus an automatic team-selection engine that assembles the strongest possible team from a set of position + main-skill requirements. Each player carries a `name`, a `position` (`defender`, `midfielder`, `forward`), and one or more skill ratings (`defense`, `attack`, `speed`, `stamina`, `strength`) with a numeric value. The API exposes a complete player CRUD workflow and a `POST /api/team/process` endpoint that ranks and selects players deterministically — ordering candidates by their value in the requested main skill and falling back to their highest-rated skill when the requested skill is absent. It is a backend-only, framework-conventional Laravel application backed by SQLite, with no third-party frontend.

---

## Features

- **Player CRUD** — list, fetch, create, update, and delete players.
- **Player skills** — each player has one or more named skills with a numeric value, returned in a consistent nested structure.
- **Team selection** — ranked, non-repeating selection of players from position + main-skill requirements.
- **Validation** — restricts position/skill values, requires at least one skill, rejects duplicate skills and duplicate requirement combinations, and refuses insufficient roster depth.
- **Authentication** — the `DELETE` endpoint is protected by a configuration-driven bearer token; Sanctum provides the default `/api/user` route.

---

## Architecture

```mermaid
flowchart LR
    CLIENT[HTTP Client / cURL / Tests] -->|JSON over HTTP| API[Laravel API /api/*]
    API --> CTRL[PlayerController]
    CTRL --> MOD[Player & PlayerSkill models]
    MOD --> DB[(SQLite database)]
```

Player management flows through a single controller over two related tables. Skills are eager-loaded on every player model, so reads and writes always ship the full nested structure.

---

## Team Selection

`POST /api/team/process` implements a ranked, non-repeating selection algorithm.

```mermaid
flowchart LR
    REQ[[For each requirement]] --> LOAD[Load players in position, not yet selected]
    LOAD --> CHECK{Enough players?}
    CHECK -- No --> ERR[400: Insufficient players for position]
    CHECK -- Yes --> SORT[Sort by mainSkill value; else highest skill value]
    SORT --> PICK[Take top N]
    PICK --> MARK[Mark players as selected]
    MARK --> NEXT{More requirements?}
    NEXT -- Yes --> REQ
    NEXT -- No --> OUT[Return selected team JSON]
```

**Request**

```json
[
  { "position": "defender", "mainSkill": "speed", "numberOfPlayers": 1 },
  { "position": "forward", "mainSkill": "attack", "numberOfPlayers": 2 }
]
```

**Steps**

1. Accept the array of requirements; reject empty requests.
2. Reject duplicate `position + mainSkill` combinations.
3. For each requirement, fetch players matching the `position` that have not already been selected.
4. Verify the available roster meets `numberOfPlayers`; otherwise return `400`.
5. Rank candidates by their value in `mainSkill`, falling back to their highest skill value when the requested skill is missing.
6. Select the top `numberOfPlayers` and mark them as used so no player appears twice.
7. Return the selected team.

**Response**

```json
[
  {
    "name": "Defender A",
    "position": "defender",
    "playerSkills": [ { "skill": "speed", "value": 95 } ]
  }
]
```

> **Note:** ranking uses the requested main skill (or the player's highest skill as a fallback), while the response includes the full skill list for each selected player.

---

## API Reference

All endpoints are served under the `/api` prefix.

| Method | Endpoint            | Description                        | Authentication |
| ------ | ------------------- | ---------------------------------- | -------------- |
| GET    | `/api/player/`      | List all players with skills       | None           |
| GET    | `/api/player/{id}`  | Fetch a single player with skills  | None           |
| POST   | `/api/player/`      | Create a player with skills        | None           |
| PUT    | `/api/player/{id}`  | Update a player and replace skills | None           |
| DELETE | `/api/player/{id}`  | Delete a player and its skills     | Bearer token   |
| POST   | `/api/team/process` | Select a team from requirements    | None           |

### Create a player — `POST /api/player/`

**Request**

```json
{
  "name": "Alice",
  "position": "defender",
  "playerSkills": [
    { "skill": "attack", "value": 60 },
    { "skill": "speed", "value": 80 }
  ]
}
```

**Response `201`**

```json
{
  "id": 1,
  "name": "Alice",
  "position": "defender",
  "playerSkills": [
    { "id": 1, "skill": "attack", "value": 60, "playerId": 1 },
    { "id": 2, "skill": "speed", "value": 80, "playerId": 1 }
  ]
}
```

### Update — `PUT /api/player/{id}`

Body has the same shape as create. Existing skills are replaced, and the `200` response reflects the newly persisted skills.

### Delete — `DELETE /api/player/{id}`

Requires the token as a bearer header:

```
Authorization: Bearer <PLAYER_API_TOKEN>
```

Response `200`:

```json
{ "message": "Player deleted successfully" }
```

### Error responses

| Status | Condition |
| ------ | --------- |
| `400`  | Invalid position/skill, no skills, duplicate skill, duplicate requirement combination, or insufficient players |
| `401`  | Missing/incorrect token on `DELETE` |
| `404`  | Player not found, or unmatched route |
| `429`  | API rate limit (60 requests/minute per IP) |

---

## Tech Stack

| Layer          | Technology                   | Version (source)        |
| -------------- | ---------------------------- | ----------------------- |
| Language       | PHP                          | `^8.1` (`composer.json`) |
| Framework      | Laravel                      | `^9.14` (`composer.json`) |
| Authentication | Laravel Sanctum              | `^2.15` (`composer.json`) |
| CORS           | fruitcake/laravel-cors       | `^3.0` (`composer.json`) |
| HTTP client    | guzzlehttp/guzzle            | `^7.4.3` (`composer.json`) |
| Database       | SQLite                       | —                       |
| Testing        | PHPUnit                      | `^9.5.20` (`composer.json`) |

---

## Database

- **Engine:** SQLite. A ready, migrated database ships at `database/database.sqlite`. PHPUnit tests run against an in-memory SQLite database (`:memory:`, see `phpunit.xml`), so tests never touch the on-disk file.

### Tables

| Table | Key columns |
| ----- | ----------- |
| `players` | `id` (PK), `name`, `position`, `created_at`, `updated_at` |
| `player_skills` | `id` (PK), `skill`, `value`, `player_id` (FK → `players.id`, cascade delete) |
| `users` / `personal_access_tokens` | Laravel Sanctum scaffolding |
| `password_resets` / `failed_jobs` | Laravel scaffolding |

### Relationship

```mermaid
erDiagram
    PLAYERS ||--o{ PLAYER_SKILLS : has
    PLAYERS {
        bigint id PK
        string name
        string position
    }
    PLAYER_SKILLS {
        bigint id PK
        string skill
        tinyint value
        bigint player_id FK
    }
```

`Player` has many `PlayerSkill` (eager-loaded by default). Skills are replaced on update and cascaded on player delete.

---

## Project Structure

```text
RestFullPlayerApi/
├── app/
│   ├── Enums/
│   │   ├── PlayerPosition.php   # defender | midfielder | forward
│   │   └── PlayerSkill.php      # defense | attack | speed | stamina | strength
│   ├── Http/
│   │   ├── Controllers/PlayerController.php  # all player + team endpoints
│   │   └── Kernel.php
│   ├── Models/
│   │   ├── Player.php           # name, position, hasMany skills
│   │   └── PlayerSkill.php      # skill, value, belongsTo player
│   └── Providers/
├── config/services.php          # PLAYER_API_TOKEN configuration
├── database/
│   ├── migrations/              # players, player_skills, users, etc.
│   └── database.sqlite          # provided SQLite database (empty, migrated)
├── routes/api.php               # API route definitions
├── tests/Feature/               # player CRUD + team feature tests
├── .env.example
├── composer.json / composer.lock
├── phpunit.xml
└── webpack.mix.js
```

---

## Installation

### Prerequisites

- PHP **8.1+**
- Composer

### Setup

```bash
git clone https://github.com/HidayahMF/RestFullPlayerApi.git
cd RestFullPlayerApi
composer install
cp .env.example .env
php artisan key:generate
```

`.env.example` is pre-configured for **SQLite**, and the provided, already-migrated `database/database.sqlite` is used by default — so **no migration step is required** to run the API.

### Run

```bash
php artisan serve --port=3000
```

The API is available at `http://localhost:3000/api/...` (e.g. `GET http://localhost:3000/api/player/`).

---

## Testing

```bash
php artisan test
```

**Result: `30 passed`**

The suite (in-memory SQLite) covers:

| Area | Coverage |
| ---- | -------- |
| **Create** | Valid creation & response, persistence, invalid position/skill, no skills, duplicate skills |
| **List / Show** | Empty list, list with data, show single player, show missing (`404`) |
| **Update** | Update existing, replace skills in response, missing (`404`), invalid position, duplicate skills |
| **Delete** | Requires token (`401`), deletes with valid token, missing (`404`) |
| **Team** | Best-player selection, non-reuse, insufficient players, duplicate requirements, empty requirements, skill fallback |

---

## Security

- **Token handling** — the `DELETE` token is configuration-driven (`PLAYER_API_TOKEN` → `config('services.player_api_token')`), not hard-coded in the controller. Set a unique token per deployment.
- **`.env` usage** — `.env` is git-ignored and untracked; only the secret-free `.env.example` is committed. Never commit real tokens or keys.
- **CORS** — permissive for all origins so the public API is consumable from any client; tighten `allowed_origins` in production if needed.
- **Rate limiting** — API routes are throttled at 60 requests/minute per IP.
- **Sanctum** — installed; provides the default `GET /api/user` route. Player CRUD and team endpoints are intentionally unauthenticated (except `DELETE`).

---

## Challenge Constraints

- PHP `8.1`
- Laravel `>= 9.14`
- SQLite
- No additional libraries beyond the Laravel defaults
- `composer.json` and `composer.lock` must not be modified
- The provided database structure must not be changed (no extra migrations/seeders)

---

## Future Improvements

- Broader integration testing, including a dedicated team-selection unit suite.
- API versioning (e.g., `/api/v1/...`).
- Optional auth scope for create/update operations.
- DB-level constraints (check/enum) for position and skill values in non-challenge deployments.
- Production deployment automation (Docker + CI) and tightened CORS for a hosted environment.

---

## Author

**Hidayah MF** — GitHub: [@HidayahMF](https://github.com/HidayahMF)
