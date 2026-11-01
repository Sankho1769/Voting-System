# VerityVote — UI / UX Design System Specification

## 1. Visual Direction: Reference 1 Image Identity + Reference 2 CricHeroes Application Experience

VerityVote combines two distinct inspirations:
1. **Reference 1 (The Visual Reference Image):** Defines the exact visual identity:
   - High-fashion editorial serif typography (`Instrument Serif`, `Playfair Display`) paired with clean sans-serif (`Plus Jakarta Sans`).
   - Distinctive color palette: Lavender `#CBA8F5`, Light Lavender `#E4CCFF`, Deep Purple `#7026C9`, Bright Purple `#A749F5`, Neon Lime `#D7FF36`, Vermilion `#F04432`, Near Black Ink `#100A18`, Pure White `#FFFFFF`.
   - Signature 3D artwork: Intertwined glossy purple and vermilion loops, floating 3D neon-lime spheres, orbit lines, and a perspective-tilted 3D floating ballot card with Candidate A (checked with neon-lime checkbox and checkmark) and Candidate B.
   - Master editorial headline: "Your voice. Your vote." (Ink, purple gradient, neon lime).
   - Three bottom portal cards: Voter (pale lavender surface `#F4ECFF`, purple badge), Candidate (pale lime surface `#EEFFA3`, olive badge), and Admin (pale pink surface `#FFF0F3`, coral vermilion badge), each with circular arrow buttons.
   - Light and dark themes (lavender canvas vs midnight purple-black `#0C0614` canvas).
2. **Reference 2 (CricHeroes Multi-Event Hub):** Defines how the application works:
   - Multi-election discovery hub (`/explore`) rather than a single election website.
   - 8 campus categories: Student Council, College President, Department, Class Representative, Club & Society, Committee, University / Senate, Special Ballots.
   - Tabbed scopes: All Elections, Live & Active (with live pulsing green badge), For You (personalized whitelist for logged-in voters), Upcoming, Completed, Results.
   - Real-time client search and category carousel pills.
   - Match-style compact election cards with countdown timers, candidate stacks, and live turnout badges.
   - Dedicated match-center election details page (`/election?id=...`).
   - Mobile bottom navigation bar for quick access across devices.

---

## 2. Color Palette & Design Tokens

### 2.1 Brand Tokens
| Token | Hex Value | Usage |
|---|---|---|
| `--color-canvas-lavender` | `#E4CCFF` / `#CBA8F5` | Global light mode canvas background |
| `--color-canvas-dark` | `#0C0614` | Global dark mode midnight purple-black canvas |
| `--brand-purple` | `#7026C9` | Primary brand purple, logos, active states |
| `--brand-purple-light` | `#A749F5` | Bright purple gradients and interactive accents |
| `--accent-lime` | `#D7FF36` | High-contrast neon lime spheres, checkmarks, primary CTA circles |
| `--vermilion` | `#F04432` | High-importance indicators, deadlines, Admin portal accent |
| `--surface-lavender` | `#F4ECFF` | Voter portal surface, subtle badges |
| `--surface-lime` | `#EEFFA3` | Candidate portal surface |
| `--surface-pink` | `#FFF0F3` | Admin portal surface |

---

## 3. Typography Hierarchy

| Style Role | Font Family | Size | Weight | Line Height |
|---|---|---|---|---|
| **Master Hero Headline** | `Instrument Serif`, `Playfair Display`, serif | 5.5rem (88px) | 700 Bold | 0.95 |
| **Page H1** | `Instrument Serif`, `Playfair Display`, serif | 2.75rem (44px) | 700 Bold | 1.15 |
| **Section H2** | `Instrument Serif`, `Playfair Display`, serif | 2.25rem (36px) | 700 Bold | 1.25 |
| **Card H3** | `Instrument Serif`, `Playfair Display`, serif | 1.55rem (25px) | 700 Bold | 1.3 |
| **Body Copy** | `Plus Jakarta Sans`, sans-serif | 1.0rem (16px) | 400 Regular | 1.55 |
| **Monospace / Receipts** | `JetBrains Mono`, monospace | 0.85rem (13.6px) | 500 Medium | 1.4 |

---

## 4. Key Interface Experiences

### 4.1 Master Homepage (`/`)
- Split hero: Left column contains eyebrow tag (`COLLEGE VOTING PLATFORM`), master editorial headline ("Your voice. Your vote."), subtitle, ink pill CTA button with neon lime arrow circle, and stacked editorial tag lists.
- Right column: High-fidelity inline SVG 3D sculpture matching Reference 1 with glossy purple ribbon, glossy vermilion loop, orbital ring, floating neon-lime spheres, and 3D ballot card.
- Three Reference Portal Cards (Voter, Candidate, Admin).
- CricHeroes-inspired live & active featured elections strip.
- 4-step "How It Works" interactive breakdown.
- Key platform metrics (100% ballot anonymity, 0% duplicate votes, ACID protected).

### 4.2 Central Multi-Election Hub (`/explore`)
- Instant client-side search across title, department, position, and contenders.
- Category carousel pills for instant filtering.
- Scoped tabs: All Elections, Live & Active, For You, Upcoming, Completed, Results.
- Match cards with live pulsing dot, countdown clocks, candidate count, and direct action buttons.

### 4.3 Match Center Election Details (`/election?id=...`)
- Event banner with status pill, category, and live countdown timer.
- Contender showcase with manifestos and candidate IDs.
- Voter whitelist rules and cryptographic receipt guarantee.

### 4.4 Candidates Directory (`/candidates`)
- Campus contenders filterable by name, election, and department.

### 4.5 Three Login Portals (`/login`, `/login/admin`, `/login/candidate`, `/login/voter`)
- Consistent styling with password toggle, CSRF protection, and role mismatch gatekeepers.
- Supports test credentials (`Shubham` / `Shubham56ty2`).

---

## 5. Responsive Design Architecture & 11 Viewport Support

The application is rigorously designed, tested, and verified across 11 distinct viewports:

| Viewport Width | Device Category | Key Responsive Behaviors |
|---|---|---|
| **320px** | Ultra-compact phone (iPhone SE / legacy) | Zero horizontal overflow guarantee (`scrollWidth == clientWidth`). Header elements adapt to single-line branding + sign-in pill. Hero typography fluid at 2.15rem. Cards 100% width. Fixed bottom app navigation bar active (56px). |
| **360px** | Standard compact Android | Scaled typography (2.25rem headline), 0.75rem container padding, compact portal cards. |
| **375px** | iPhone mini | Fluid card widths, full-width CTA pill buttons, horizontal-scroll category bar. |
| **390px** | iPhone 14 / modern Android | Primary mobile reference. Single-column stacked cards, hero sculpture below headline, mobile bottom navigation bar. |
| **430px** | iPhone 14 Pro Max | Spacious mobile layout, 2.85rem headline, card padding 1.25rem. |
| **600px** | Small tablet / Foldable | 2-column stats grid, single-column portal cards, fluid search inputs. |
| **768px** | iPad portrait / medium tablet | Single-column hero, 2-column grid cards, mobile bottom navigation bar active. |
| **1024px** | iPad landscape / small laptop | Desktop top navigation bar active, mobile bottom nav hidden. 2-column cards, hero left-aligned. |
| **1280px** | Standard laptop | Container max-width 1240px, 3-column portal cards side-by-side, split hero. |
| **1440px** | Desktop primary reference | Exact Reference 1 poster composition: split hero with 3D sculpture on right, 3 portal cards in single row. |
| **1920px** | Ultra-wide / Full HD | Container max-width 1480px, hero headline up to 6.2rem, centered ergonomic layout. |

### 5.1 Mobile Bottom Navigation Bar
On touch devices (`<= 768px`), a native-app style bottom navigation bar is pinned to the viewport:
- **Tabs:** Home, Elections (`/explore`), Candidates (`/candidates`), Sign In / Portal (`/login` or role dashboard), and Theme Toggle.
- **Clearance:** Body receives dynamic padding-bottom (66px to 74px) so no footer or card content is obscured.
- **Theme integration:** Includes instant client-side theme toggling with smooth transitions.

---

## 6. Keyframe Animations & Micro-Interactions

VerityVote implements smooth, high-performance CSS animations designed to evoke tactile physical presence without layout shift or jank:

### 6.1 Keyframe Animations
- **`@keyframes heroFadeUp`**: Soft translateY entrance (20px to 0) with opacity fade for hero headline and eyebrow tag on initial page render (`cubic-bezier(0.16, 1, 0.3, 1)`).
- **`@keyframes floatRibbon`**: Continuous gentle floating loop on the 3D ribbon sculpture (7s duration, `ease-in-out alternate`, subtle translateY -9px and rotate 0.6deg).
- **`@keyframes floatSphere`**: Subtle asynchronous floating on decorative neon lime spheres (4.5s and 5.5s alternate reverse).
- **`@keyframes orbitSpin`**: Continuous slow orbital rotation on decorative lime orbit rings (45s linear infinite, centered at 360px 300px).
- **`@keyframes cardReveal`**: Staggered fade-up entrance for portal cards with progressive delays (0.08s, 0.16s, 0.24s).
- **`@keyframes pulseLive`**: Pulsing radar glow for active election badges and live dots (`box-shadow` ripple in `#22C55E`).

### 6.2 Micro-Interactions & Hover Physics
- **Portal Cards:** Elevate by `translateY(-6px)` with dynamic box-shadow expansion. The circular arrow button shifts right by `4px` and inverts to dark ink surface on hover.
- **Action Buttons (`.btn-dark`, `.btn-primary`):** Pill arrow circles translate by `4px` on hover. Active press state applies subtle `scale(0.98)` feedback.
- **Category Pills:** Smooth background and text color transition with `translateY(-1px)` hover lift and `translateY(1px)` active press.

---

## 7. Accessibility & Reduced Motion Compliance

VerityVote complies with WCAG 2.1 Level AA accessibility standards:
- **`@media (prefers-reduced-motion: reduce)`**: Completely disables all keyframe animations (`animation-duration: 0.01ms !important; animation-iteration-count: 1 !important`), neutralizes CSS transforms, sets transition durations to 0.01ms, and restores instant scrolling.
- **Color Contrast:** All text pairings (deep purple on lavender, dark ink on white, dark ink on lime) maintain minimum 4.5:1 contrast ratios.
- **ARIA & Keyboard Navigation:** Dropdowns, modals, tabs, and form controls have explicit ARIA attributes, semantic landmarks, and visible focus rings.
