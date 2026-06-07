# Design System Inspired by FastWork

## 1. Visual Theme & Atmosphere

FastWork embodies a modern, tech-forward aesthetic designed for enterprise service businesses. The design system balances approachability with professional credibility through a vibrant teal-and-orange color story paired with clean, spacious layouts. Isometric illustrations and playful icons convey innovation and team collaboration, while dark overlays and gradient accents add sophistication. The atmosphere is energetic yet organized—suggesting both speed and reliability for businesses managing complex operations. This design language positions FastWork as a contemporary, forward-thinking platform that simplifies operational chaos.

**Key Characteristics**
- Bright, saturated teal as primary brand identity
- Warm orange/gold accents for secondary calls-to-action
- Clean white and light gray surfaces for clarity
- Generous whitespace and breathing room in layouts
- Isometric 3D illustrations emphasizing interconnected workflows
- Modern sans-serif typography (Roboto) for accessibility
- Rounded corners throughout for friendliness
- Strategic use of shadows for depth and focus

## 2. Color Palette & Roles

### Primary
- **Brand Teal** (`#1ABC9C`): Primary CTA buttons, links, brand identity, and key interactive elements. Used 350+ times across the platform.
- **Teal Light** (`#7BDCB5`): Soft background tints and hover states for primary elements.
- **Mint Green** (`#00D084`): Accent for active states and success indicators.

### Accent Colors
- **Warm Orange** (`#FF6900`): Secondary action buttons and highlight elements.
- **Gold** (`#FCB900`): Premium or featured content badges and overlays.
- **Soft Yellow** (`#FFEEAA`): Light background fills and warning contexts.
- **Rose Pink** (`#F78DA7`): Decorative accents and emotional highlights.

### Interactive
- **Light Mint Background** (`#D1F2DE`): Button hover states and light interactive backgrounds.

### Neutral Scale
- **Dark Charcoal** (`#333333`): Primary text and headings (used 469 times).
- **Black** (`#000000`): High-contrast text and borders (used 85 times).
- **Dark Gray** (`#777777`): Secondary text and subtle elements (used 56 times).
- **Medium Gray** (`#888888`): Tertiary text and disabled states (used 22 times).
- **Light Gray** (`#AAAAAA`): Placeholder text and subtle borders (used 6 times).
- **Off White** (`#F9F9F9`): Subtle background tints.
- **White** (`#FFFFFF`): Primary surfaces and overlays (used 59 times).

### Surface & Borders
- **Border Gray** (`#CCCCCC`): Input borders and subtle dividers.
- **Neutral Medium** (`#ABB8C3`): Navigation dividers and secondary borders.

### Semantic / Status
- **Warning** (`#F39F1E`, `#F29E16`, `#FCB900`): Alert states, warnings, and cautionary information.
- **Danger / Error** (`#CF2E2E`): Critical errors and destructive actions.

## 3. Typography Rules

### Font Family
**Primary Font:** Roboto (sans-serif)
Fallback stack: `Roboto, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`

**Secondary Font:** Arial (sans-serif)
Fallback stack: `Arial, Helvetica, sans-serif`

### Hierarchy

| Role | Font | Size | Weight | Line Height | Letter Spacing | Notes |
|---|---|---|---|---|---|---|
| Display / H1 | Roboto | 35px | 700 | 55px | Normal | Page hero headlines; commanding presence |
| Heading / H2 | Roboto | 22px | 600 | 26px | Normal | Section headings; strong visual hierarchy |
| Heading / H3 | Roboto | 16px | 700 | 26px | Normal | Subsection titles; card headers |
| Body Text | Roboto | 18px | 400 | 24px | Normal | Primary content and descriptions |
| Body Small | Roboto | 16px | 400 | 28px | Normal | Secondary content, extended reading |
| Caption | Roboto | 12px | 400 | 15.6px | Normal | List items, metadata, timestamps |
| Button | Arial | 12px | 400 | 15.6px | Normal | CTA labels; rounded button text |
| Input / Large | Roboto | 70px | 700 | 100px | Normal | Large input fields and prominent counters |
| Input / Standard | Roboto | 18px | 400 | 24px | Normal | Form fields and text areas |

### Principles
- **Hierarchy through weight:** Bold (700) for primary headings, medium (600) for section breaks, regular (400) for body.
- **Generous line height:** 1.5x base size for comfortable reading of longer content.
- **Size restraint:** Only three distinct body sizes to maintain visual consistency.
- **Roboto primary:** Modern, accessible, and optimized for screen rendering.
- **All-caps buttons:** Some CTAs use uppercase for emphasis (inferred from interaction patterns).

## 4. Component Stylings

### Buttons

**Primary Button (Teal CTA)**
- **Background:** `#1ABC9C`
- **Text Color:** `#FFFFFF`
- **Font:** Arial, 12px, weight 400, line-height 15.6px
- **Padding:** `15px 20px`
- **Border Radius:** `200px`
- **Border:** None
- **Box Shadow:** None
- **Hover State:** Background `#00D084`, light mint tint overlay

**Secondary Button (Orange/Gold)**
- **Background:** `#FF6900` or `#FCB900`
- **Text Color:** `#FFFFFF`
- **Font:** Arial, 12px, weight 400, line-height 15.6px
- **Padding:** `15px 20px`
- **Border Radius:** `200px`
- **Border:** None
- **Box Shadow:** None
- **Hover State:** Background lightens by 10%, shadow `rgba(0, 0, 0, 0.15) 0px 2px 8px`

**Ghost Button (Transparent)**
- **Background:** Transparent (`rgba(0, 0, 0, 0)`)
- **Text Color:** `#333333`
- **Font:** Arial, 12px, weight 400, line-height 15.6px
- **Padding:** `15px 20px`
- **Border Radius:** `200px`
- **Border:** `1px solid #333333`
- **Box Shadow:** None
- **Hover State:** Background `#F9F9F9`, text darkens to `#000000`

### Cards & Containers

**Standard Card**
- **Background:** `#FFFFFF`
- **Text Color:** `#333333`
- **Font:** Roboto, 16px, weight 400, line-height 28px
- **Padding:** `24px`
- **Border Radius:** `0px` (flat) or `8px` (minimal rounding)
- **Border:** `1px solid #CCCCCC`
- **Box Shadow:** None (flat design) or `rgba(0, 0, 0, 0.08) 0px 2px 12px` (elevated)
- **Hover State:** Border color shifts to `#1ABC9C`, subtle lift with shadow

**Overlay Card (Dark Theme)**
- **Background:** `rgba(0, 0, 0, 0.7)` with gradient overlay
- **Text Color:** `#FFFFFF`
- **Font:** Roboto, 16px, weight 400, line-height 28px
- **Padding:** `32px 36px`
- **Border Radius:** `12px`
- **Border:** None
- **Box Shadow:** `rgba(0, 0, 0, 0.3) 0px 8px 24px`

**Stats / Metric Container**
- **Background:** `#F9F9F9` or semi-transparent overlay
- **Text Color:** `#333333` (label) and `#1ABC9C` (metric)
- **Font:** Roboto, 18px body / 35px metric
- **Padding:** `24px 28px`
- **Border Radius:** `8px`
- **Border:** `1px solid #CCCCCC`
- **Box Shadow:** None

### Inputs & Forms

**Text Input (Standard)**
- **Background:** `rgba(0, 0, 0, 0.035)` (very light tint)
- **Text Color:** `#555555`
- **Font:** Roboto, 18px, weight 400, line-height 24px
- **Padding:** `10px`
- **Border Radius:** `6.72px`
- **Border:** `1px solid #CCCCCC`
- **Box Shadow:** None
- **Focus State:** Border color `#1ABC9C`, shadow `0px 0px 0px 3px rgba(26, 188, 156, 0.1)`
- **Placeholder Color:** `#AAAAAA`

**Text Input (Dark / Search)**
- **Background:** `rgba(0, 0, 0, 0.035)`
- **Text Color:** `#FFFFFF`
- **Font:** Roboto, 18px, weight 400, line-height 24px
- **Padding:** `10px`
- **Border Radius:** `6.72px`
- **Border:** `1px solid #CCCCCC`
- **Box Shadow:** None
- **Focus State:** Border `#1ABC9C`, inner glow

**Large Counter Input**
- **Background:** Transparent
- **Text Color:** `#333333`
- **Font:** Roboto, 70px, weight 700, line-height 100px
- **Padding:** `18px 70px 8px 0px`
- **Border Radius:** `0px`
- **Border:** None
- **Box Shadow:** None
- **Underline (optional):** `1px solid #1ABC9C` for visual anchor

### Navigation

**Top Navigation Bar**
- **Background:** `#FFFFFF`
- **Text Color:** `#333333`
- **Font:** Roboto, 16px, weight 400, line-height 28px
- **Height:** `70px`
- **Padding:** `0px 40px`
- **Border Bottom:** `1px solid #CCCCCC` (optional)
- **Logo Teal:** `#1ABC9C`
- **Logo Orange:** `#FF6900`
- **Hover State:** Text `#1ABC9C`, underline `1px solid #1ABC9C`

**Navigation Link (Active)**
- **Text Color:** `#1ABC9C`
- **Border Bottom:** `3px solid #1ABC9C`
- **Font Weight:** 600

**Dropdown Menu**
- **Background:** `#FFFFFF`
- **Border:** `1px solid #CCCCCC`
- **Border Radius:** `8px`
- **Box Shadow:** `rgba(0, 0, 0, 0.15) 0px 0px 20px 0px`
- **Item Padding:** `12px 16px`
- **Item Hover:** Background `#F9F9F9`, text `#1ABC9C`

### Links & Action Icons

**Circular Icon Link (Floating)**
- **Background:** `#FFFFFF`
- **Icon Color:** `#1ABC9C`
- **Dimensions:** `60px × 60px`
- **Border Radius:** `50%`
- **Box Shadow:** `rgba(0, 0, 0, 0.15) 0px 0px 36px 0px`
- **Hover State:** Shadow expands to `rgba(0, 0, 0, 0.2) 0px 0px 48px`, scale 1.05

## 5. Layout Principles

### Spacing System
**Base Unit:** 4px

**Scale:**
- `4px` — Micro spacing (internal button padding, tight gaps)
- `12px` — Compact spacing (list item gaps, form field spacing)
- `16px` — Standard spacing (component padding, light margins)
- `24px` — Comfortable spacing (section margins, card padding)
- `36px` — Generous spacing (section breaks, feature sections)
- `40px` — Large sections (between major content blocks)
- `52px` — Extra-large spacing (hero to content transition)
- `76px` — Page-level spacing (top/bottom margins)
- `92px` — Dramatic spacing (between hero and secondary content)

**Usage Context:**
- Buttons and small components: `12px` to `16px`
- Card internals: `24px` padding
- Section breaks: `36px` to `52px` margin
- Hero sections: `76px` to `92px` margin

### Grid & Container
**Max Width:** 1260px (observed from large input width)

**Layout Strategy:**
- Desktop: Full-width with generous side margins (40px–76px)
- Column Strategy: 12-column grid implied; feature sections use 2–4 columns for content blocks
- Section Patterns: Alternating full-width blocks with centered content containers

**Gutter:** 24px between columns

### Whitespace Philosophy
Abundant whitespace defines the design language. Large margins between sections create breathing room and guide the eye through content hierarchies. Empty space is treated as a design element, not wasted real estate. This approach reinforces the platform's modern, uncluttered positioning and supports cognitive load reduction for busy service managers.

### Border Radius Scale
- **Button / Pill:** `200px` (highly rounded, pill-shaped)
- **Avatar / Circle:** `50%` or `35px` to `38px` (fully circular)
- **Input / Cards:** `6.72px` (subtle, geometric softness)
- **Containers / Overlays:** `8px` to `12px` (light rounding for modern flat design)
- **Flat Elements:** `0px` (no rounding for maximalist clean aesthetic)

## 6. Depth & Elevation

| Level | Treatment | Use |
|---|---|---|
| Flat (Base) | No shadow | Cards on light backgrounds, neutral surfaces |
| Raised (Subtle) | `rgba(0, 0, 0, 0.08) 0px 2px 12px` | Hoverable cards, interactive elements |
| Elevated (Medium) | `rgba(0, 0, 0, 0.15) 0px 0px 20px 0px` | Dropdowns, modals, floating action elements |
| High (Prominent) | `rgba(0, 0, 0, 0.2) 0px 4px 24px` | Tooltips, floating buttons, hero overlays |
| Floating (Dramatic) | `rgba(0, 0, 0, 0.3) 0px 8px 36px` | Sticky headers, modal dialogs, priority alerts |

**Shadow Philosophy:**
Shadows are used sparingly and strategically to establish depth hierarchy without visual clutter. The primary shadow palette relies on semi-transparent black (`rgba(0, 0, 0, 0.15)`) to maintain sophistication. Floating elements (circular icon links, chat widgets) receive more pronounced shadows to indicate interactivity and pull focus. Dark overlays (demo modals, gradient backgrounds) use shadows more liberally for contrast. The approach is restrained compared to skeuomorphic systems—shadows support function, not decoration.

## 7. Do's and Don'ts

### Do
- **Use teal (`#1ABC9C`) for all primary CTAs and active states.** This is the brand anchor and should dominate interactive affordances.
- **Apply ample padding and margin around content.** White space is your ally; aim for 24px–40px between sections.
- **Leverage Roboto for body text and Roboto bold for headings.** Consistency in typography strengthens brand recognition.
- **Round button corners generously (`200px`)** to create the signature pill-shaped aesthetic.
- **Use circular icon links (`50%` radius, 60px) with elevation shadows** for floating actions like chat or demo requests.
- **Employ the mint/teal color palette for accents, success states, and hover effects.**
- **Apply border color `#1ABC9C` on input focus** to guide user attention.
- **Layer dark overlays with gradient transitions** for premium feature sections (demos, modals).
- **Test contrast ratios.** Dark text on light backgrounds should meet WCAG AA (4.5:1 minimum).

### Don't
- **Avoid primary buttons in orange without context.** Orange is secondary; use teal for main CTAs.
- **Don't use shadows heavier than `rgba(0, 0, 0, 0.2)`.** Over-shadowing reads as dated or clumsy.
- **Don't mix serif and sans-serif fonts arbitrarily.** Keep typography predictable and hierarchy clear.
- **Avoid padding smaller than `12px` inside components.** Cramped spacing reduces scannability.
- **Don't remove border-radius entirely from interactive elements.** Pill shapes (200px) and circles (50%) are signature affordances.
- **Avoid using gray text lighter than `#777777` for body copy.** Accessibility and readability suffer below that threshold.
- **Don't nest more than 3 font weights in a single layout.** Simplicity prevents visual noise.
- **Avoid hard edges on overlay cards.** Cards should have `8px` to `12px` border-radius for visual softness.
- **Don't duplicate shadow styles across different elevation levels.** Each level should feel distinct in depth.

## 8. Responsive Behavior

### Breakpoints

| Name | Width | Key Changes |
|---|---|---|
| Mobile | < 640px | Single-column layout; reduce padding to 16px–24px; hero text 22px; buttons full-width; hide secondary nav |
| Tablet | 640px–1024px | Two-column grids; padding 24px–36px; nav collapses to hamburger; font sizes reduce 10%; feature sections stack |
| Desktop | 1024px–1440px | Full 12-column grid; max-width 1260px; padding 40px–76px; all features visible; multi-column layouts active |
| Large (4K) | > 1440px | Center container, increase side margins; consider larger font sizes (+2px); expand whitespace proportionally |

### Touch Targets
- **Minimum interactive size:** `44px × 44px` (buttons, icon links)
- **Comfortable spacing between targets:** `16px` minimum
- **Button padding on mobile:** `16px 24px` (larger than desktop)
- **Input height on mobile:** Minimum `48px` for easy tapping
- **Icon size on mobile:** `24px` to `32px` (up from `16px` on desktop)

### Collapsing Strategy
- **Hero section:** Reduce Display text from `35px` to `22px` on mobile; scale illustration down or replace with simpler graphic.
- **Multi-column layouts:** Stack to single column on tablet; revert to 2 columns at `1024px`.
- **Navigation:** Collapse to hamburger menu below `1024px`; show full horizontal menu on desktop.
- **Cards:** On mobile, reduce padding from `24px` to `16px`; increase bottom margin for touch spacing.
- **Floating buttons:** Reposition to bottom-right corner on mobile with `16px` margin; maintain `60px × 60px` size.
- **Forms:** Single input per row on mobile; allow 2-column layouts on tablet and desktop.
- **Stats containers:** Stack vertically on mobile (`1` column); arrange in 2–4 columns on larger screens.

## 9. Agent Prompt Guide

### Quick Color Reference
Use these colors as your north star for FastWork UI implementation:

- **Primary CTA:** Teal (`#1ABC9C`) — buttons, links, active states, brand identity
- **Secondary CTA:** Orange/Gold (`#FF6900` or `#FCB900`) — secondary actions, accent highlights
- **Success / Active:** Mint Green (`#00D084`) — checkmarks, active toggles, confirmation states
- **Background (Light):** Off White (`#F9F9F9`) — card backgrounds, light surface tints
- **Background (Dark Overlay):** Black with opacity (`rgba(0, 0, 0, 0.7)`) — premium modals, feature demos
- **Heading Text:** Dark Charcoal (`#333333`) — all primary text content
- **Body Text:** Dark Gray (`#777777`) — secondary descriptions, metadata
- **Disabled / Placeholder:** Light Gray (`#AAAAAA`) — inactive inputs, placeholder text
- **Warning / Alert:** Gold/Orange (`#FCB900` or `#F39F1E`) — warning messages, cautions
- **Error / Danger:** Red (`#CF2E2E`) — critical errors, destructive confirmations
- **Border / Divider:** Border Gray (`#CCCCCC`) — input borders, subtle separators
- **White Surface:** Pure White (`#FFFFFF`) — card backgrounds, modal content, primary containers

### Iteration Guide

1. **All buttons are pill-shaped.** Apply `border-radius: 200px` uniformly; no corners. Primary buttons use `#1ABC9C` background with white text; secondary use orange (`#FF6900`); ghost variants use transparent background with `#333333` text and matching border.

2. **Heading hierarchy is strict.** Use Roboto `700` for Display (`35px`), Roboto `600` for H2 (`22px`), Roboto `700` for H3 (`16px`). Body text is always Roboto `400` at `16px` or `18px` depending on context. Buttons are Arial `400` at `12px`.

3. **Whitespace is generous.** Apply minimum `24px` padding inside cards, `24px` to `40px` margin between sections, and `36px–92px` margin between major layout blocks. Never crowd content.

4. **Input focus state adds teal border and subtle glow.** On focus, change border to `#1ABC9C` and add box-shadow: `0px 0px 0px 3px rgba(26, 188, 156, 0.1)`. This guides attention without overwhelming.

5. **Shadows are subtle and purposeful.** Use `rgba(0, 0, 0, 0.15) 0px 0px 20px 0px` for dropdowns and modals. Lighter interactive elements use `rgba(0, 0, 0, 0.08) 0px 2px 12px`. No shadow stronger than `rgba(0, 0, 0, 0.2)`.

6. **Circular floating actions (chat, demo, icon links) are `60px`, fully rounded (`50%`), white background with teal icon, and elevated with `rgba(0, 0, 0, 0.15) 0px 0px 36px 0px` shadow.** They should feel like they hover above the page.

7. **Cards default to flat (`0px` shadow) with `1px solid #CCCCCC` border.** On hover, lighten the border to `#1ABC9C` and optionally add subtle shadow `rgba(0, 0, 0, 0.08) 0px 2px 12px` to indicate interactivity.

8. **Dark overlays (modals, hero sections with demos) use `rgba(0, 0, 0, 0.7)` background with white text and layered gradients.** Apply `border-radius: 12px` and `box-shadow: rgba(0, 0, 0, 0.3) 0px 8px 24px` for prominence.

9. **Mobile-first responsive approach.** Design single-column layouts first at `< 640px`; expand to 2–4 columns on tablet (`640px–1024px`) and desktop (`1024px+`). Reduce font sizes by ~10% on mobile; adjust padding from `40px` down to `16px–24px`.

10. **Navigation is always top-bar style (`70px` height, white background, `#333333` text).** Logo is `#1ABC9C` (teal) for "FW" and `#FF6900` (orange) for "FastWork." Active nav items underline in teal. Collapse to hamburger menu below `1024px` width.

11. **Forms use Roboto `18px` with `10px` padding, `6.72px` border-radius, and `1px solid #CCCCCC` border.** Focus state adds teal border and glow. Placeholder text is `#AAAAAA`. Background is `rgba(0, 0, 0, 0.035)` (very subtle tint) for definition.

12. **Stats and metric containers highlight the number in teal (`#1ABC9C`).** Use smaller gray text for labels. Apply `24px` padding, `8px` border-radius, light gray background (`#F9F9F9`), and optional `1px solid #CCCCCC` border for subtle definition.