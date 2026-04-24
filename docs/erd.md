# SafeCity ERD

Diagram encji i relacji dla aktualnego schematu z `docker/db/init/init.sql`.

```mermaid
erDiagram
    roles {
        SERIAL id PK
        VARCHAR name UK
    }

    users {
        SERIAL id PK
        VARCHAR email UK
        VARCHAR password_hash
        VARCHAR full_name
        INT role_id FK
        TIMESTAMP created_at
    }

    incident_statuses {
        SERIAL id PK
        VARCHAR name UK
    }

    incident_categories {
        SERIAL id PK
        VARCHAR name
        VARCHAR icon
        VARCHAR color
    }

    incidents {
        SERIAL id PK
        VARCHAR title
        TEXT description
        VARCHAR location
        INT category_id FK
        INT reported_by FK
        INT status_id FK
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    incident_comments {
        SERIAL id PK
        INT incident_id FK
        INT user_id FK
        TEXT body
        TIMESTAMP created_at
    }

    roles ||--o{ users : "role_id"
    incident_statuses ||--o{ incidents : "status_id"
    incident_categories ||--o{ incidents : "category_id"
    users ||--o{ incidents : "reported_by"
    incidents ||--o{ incident_comments : "incident_id"
    users ||--o{ incident_comments : "user_id"
```

## Uwagi

- `category_id` i `reported_by` są nullable, bo mają `ON DELETE SET NULL`.
- `incident_comments.incident_id` ma `ON DELETE CASCADE`, więc usunięcie incydentu usuwa też komentarze.
- `role_id` i `status_id` mają `ON DELETE RESTRICT`, co blokuje usunięcie słownika używanego przez rekordy biznesowe.
