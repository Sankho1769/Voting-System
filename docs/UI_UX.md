# VerityVote — UI / UX Design System Specification

## 1. Editorial Visual Direction & Personality
VerityVote is designed with the feeling of a **distinguished collegiate editorial publication** rather than a generic tech-startup or AI template. It emphasizes:
- Refined serif display typography for page titles and headers.
- Intentional, generous whitespace and rhythmic vertical alignment.
- A restrained, authoritative color palette featuring natural Warm Ivory, deep Emerald Green, and selective Vermilion accents.
- Tactile, accessible forms with high-contrast borders and restrained shadows.
- Distinct candidate presentation cards that respect the gravity of academic elections.

---

## 2. Color Palette & Design Tokens

### 2.1 Strict Brand Tokens
| Token | Hex Value | Role & Usage |
|---|---|---|
| **Warm Ivory** | `#F9EBD7` | Content surfaces, card backgrounds, crest accents, election detail mastheads |
| **Emerald Green** | `#046A38` | Primary actions, branding crest, active navigation, vote submission buttons |
| **Vermilion** | `#E34222` | High-importance indicators, deadlines, destructive actions, security alerts |

### 2.2 Semantic Neutrals & Surface Colors
| Token | Hex Value | Usage |
|---|---|---|
| `--color-surface-base` | `#F8F3EC` | Global canvas background (soft warm neutral) |
| `--color-surface-card` | `#FFFFFF` | Primary white card and table surface |
| `--color-surface-muted` | `#F1E9DE` | Table headers, secondary control backgrounds |
| `--color-text-primary` | `#18221D` | Deep charcoal with dark forest tint (WCAG AAA contrast 14.2:1) |
| `--color-text-secondary` | `#48564F` | Explanatory text, secondary subtitles (WCAG AA contrast 6.1:1) |
| `--color-text-muted` | `#6B7B73` | Form help text, timestamps, metadata labels |
| `--color-border-subtle` | `#E6DCCE` | Hairline card and row dividers |
| `--color-border-medium` | `#D4C6B3` | Form control outlines and table borders |
| `--color-border-focus` | `#046A38` | Visible 2px focus ring on interactive elements |

### 2.3 Status Color Tokens
- **Draft**: `#48564F` (Soft gray-green)
- **Scheduled**: `#1C4D78` (Muted navy)
- **Open / Active**: `#046A38` (Brand Emerald Green)
- **Closed**: `#8C471E` (Warm amber-brown)
- **Results Published**: `#046A38` (Emerald Green)
- **Cancelled**: `#B83217` (Deep Vermilion)

---

## 3. Typography Hierarchy

| Style Role | Font Family | Size | Weight | Line Height |
|---|---|---|---|---|
| **H1 Display** | `Newsreader`, `Georgia`, serif | 2.25rem (36px) | 600 SemiBold | 1.25 |
| **H2 Section** | `Newsreader`, `Georgia`, serif | 1.75rem (28px) | 600 SemiBold | 1.3 |
| **H3 Card Heading** | `Newsreader`, `Georgia`, serif | 1.35rem (22px) | 600 SemiBold | 1.35 |
| **Body Copy** | `-apple-system`, `Segoe UI`, Roboto, sans-serif | 1.0rem (16px) | 400 Regular | 1.55 |
| **Label / Subtitle** | `-apple-system`, `Segoe UI`, Roboto, sans-serif | 0.875rem (14px) | 600 SemiBold | 1.4 |
| **Code / Receipt Hash** | `SF Mono`, Monaco, Consolas, monospace | 0.85rem (13.6px) | 500 Medium | 1.4 |

---

## 4. Key Interface Components

### 4.1 Header & Brand Monogram
The masthead features an emerald green square with a serif "V" monogram, accompanied by the bold title `VerityVote` and an institutional subtitle. On authenticated pages, the header features a pill capsule showing user identifier, role badge, and quick security/sign-out controls.

### 4.2 Candidate Selection Cards
Contenders are presented in clickable cards with:
- Accessible radio selector with keyboard navigation support (`Space` / `Enter`).
- Candidate monogram avatar or photo thumbnail (uploaded images are strictly validated, resized, and sanitized).
- Full candidate name and manifesto excerpt.
- Active visual state: 2px emerald green border and soft green highlight (`#E8F4EE`).

### 4.3 Confirmation Modal
Before any ballot is committed to the database, a modal dialog renders the chosen candidate's name prominently, requiring deliberate secondary confirmation to eliminate accidental clicks.

### 4.4 Live Standings & Progress Bars
- Smooth CSS progress bars showing vote share percentage.
- Real-time client polling (`app.js`) at a 6-second interval when viewing an open election with live results enabled.
- Division-by-zero protection ensures `0%` renders smoothly if zero ballots have been cast.

### 4.5 Three Dedicated Login Portals Experience
- **Portal Selection Hub (`/login`)**:
  - Distinct three-card grid (Administrator, Candidate, Voter) rendered on Warm Ivory canvas (`#F8F3EC`).
  - Each card features a custom monogram crest (A, C, V), role summary, and prominent CTA button.
- **Role-Specific Login Screens (`/login/admin`, `/login/candidate`, `/login/voter`)**:
  - Distinct masthead titles, badges, and contextual help text tailored to each role.
  - Candidate portal highlights support for both assigned Candidate Codes (e.g. `CAND-01`) and institutional Roll Numbers.
- **Show/Hide Password UI**:
  - Accessible vanilla JavaScript toggle button embedded inside the password input container.
  - Dynamically updates input `type` between `password` and `text`.
  - Accessible `aria-label` updates dynamically between "Show password" and "Hide password" for screen readers.
- **Quick Switcher & Cross-Link Banners**:
  - Subtle portal-switching links below the login form allow instant navigation across portals.
  - Dual-permission alert banner in the Candidate Dashboard and Voter Dashboard links candidates directly to their ballot box when whitelisted as voters.

---

## 5. Accessibility & Motion Guidelines
- **Focus Rings**: `:focus-visible` enforces a distinct 2px emerald outline with a 2px offset.
- **Form Labels**: Every form input is paired with an explicit semantic `<label>` and ARIA attributes where needed.
- **Color Independence**: Status badges use both distinct colors and explicit textual labels (`DRAFT`, `OPEN`, `CLOSED`, `VOTED`).
- **Reduced Motion**: All CSS animations and transitions automatically drop to 0.01ms when `prefers-reduced-motion: reduce` is detected in system preferences.
