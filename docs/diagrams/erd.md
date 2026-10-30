# Entity-Relationship Diagram (ERD)

This document visualizes the complete relational data model of the VerityVote system.

```mermaid
erDiagram
    users ||--o{ elections : "creates"
    users ||--o{ election_eligibility : "assigned_to"
    users ||--o{ election_participation : "participates_in"
    users ||--o{ audit_logs : "triggers"
    users ||--o{ candidates : "links_to"
    
    elections ||--o{ candidates : "contests_in"
    elections ||--o{ election_eligibility : "enrolls"
    elections ||--o{ election_participation : "tracks"
    elections ||--o{ votes : "contains"
    
    candidates ||--o{ votes : "receives"

    users {
        int id PK
        varchar identifier UK "Roll / Employee ID"
        enum role "student|staff|candidate|election_officer|admin"
        varchar full_name
        varchar email
        varchar department
        varchar academic_year
        varchar password_hash
        tinyint must_change_password
        enum status "active|suspended|deactivated"
        datetime temp_password_expires_at
        int auth_version
        datetime created_at
        datetime updated_at
    }

    elections {
        int id PK
        varchar title
        varchar position "Single Contested Office"
        text description
        enum status "draft|scheduled|open|closed|results_published|cancelled"
        datetime opens_at
        datetime closes_at
        enum results_visibility "live_provisional|after_closure_only"
        int created_by FK
        datetime created_at
        datetime updated_at
    }

    candidates {
        int id PK
        int election_id FK
        int user_id FK "Optional linked user account"
        varchar name
        text manifesto
        varchar photo_path
        enum approval_status "pending|approved|rejected"
        int display_order
        datetime created_at
        datetime updated_at
    }

    election_eligibility {
        int id PK
        int election_id FK
        int user_id FK
        int assigned_by FK
        datetime assigned_at
    }

    election_participation {
        int id PK
        int election_id FK
        int user_id FK
        datetime voted_at
        varchar confirmation_hash UK
    }

    votes {
        int id PK
        int election_id FK
        int candidate_id FK
        datetime cast_at
    }

    audit_logs {
        int id PK
        int user_id FK
        varchar action
        varchar entity_type
        varchar entity_id
        varchar ip_address
        varchar user_agent
        text details_json
        datetime created_at
    }

    login_attempts {
        int id PK
        varchar identifier
        varchar ip_address
        datetime attempted_at
        tinyint is_successful
    }
```

## Architectural Cardinality Rules
1. **One-to-Many (`elections` &rarr; `candidates`)**: An election must have at least 2 approved candidates prior to transitioning to `open`.
2. **Many-to-Many via Whitelist (`users` &harr; `elections` through `election_eligibility`)**: A user can vote only in elections where an explicit whitelist mapping exists.
3. **Decoupled Ballot Box (`election_participation` vs `votes`)**: Participation is linked to `users(id)`, but individual choices in `votes` are linked only to `elections(id)` and `candidates(id)`, preserving voter ballot privacy.
