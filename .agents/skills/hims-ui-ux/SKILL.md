---
name: hims-ui-ux
description: Guides HIMS UI and UX changes involving Blade, Tailwind CSS, Alpine.js, layouts, responsive pages, forms, tables, modals, notifications, loading states, navigation, badges, banners, density, table overflow, accessibility, or frontend behavior. Use when modifying existing interfaces or adding screens that must match the HIMS design system and interaction contracts.
---

# HIMS UI/UX

Use this skill to extend the existing interface without turning a local request into a redesign. Inspect the rendered page, its layout, Blade partials/components, JavaScript hooks, authorization checks, and nearby tests before editing.

## Current UI Baseline

HIMS is a server-rendered Blade application using Tailwind, Alpine.js, Vite, and a shared component library under `resources/views/components/ui`.

- Authenticated screens share `layouts/app.blade.php`, the sidebar/topbar partials, flash alerts, decision confirmation, global loading overlay, and session-warning UI.
- Authentication screens use `layouts/guest.blade.php` and existing auth components. The landing and legal pages are standalone public templates, so inspect their own shells before changing them.
- Admin and Super Admin pages are view namespaces, not separate layout systems. Preserve panel-aware navigation and Gate-based visibility.
- Branding uses Inter, the `primary` clinical-blue scale, semantic `success`/`warning`/`danger` colors, neutral surfaces, restrained motion, and high data readability.

Treat these as verified current patterns and recheck the actual files before relying on exact props or behavior.

## Implementation Workflow

Find the closest existing screen -> identify its layout, components, server response, authorization, and JavaScript hooks -> reuse the established pattern -> implement every relevant state -> verify responsive, keyboard, and server-error behavior.

Do not redesign unrelated navigation, swap the component system, introduce a new frontend framework, or add a package for an interaction the existing stack already supports.

## Reuse the Actual Components

Prefer the existing `<x-ui.*>` component when its contract fits. Important current contracts include:

- `alert`: `variant`, `title`, `message`, `dismissible`
- `badge`: `status`, `variant`, `dot`
- `button`: `variant`, `size`, `href`, `type`, `icon`
- `card`: `title`, `subtitle`, `padding`; optional `header`, `actions`, and `footer` slots
- `field`: `name`, `label`, `type`, `value`, `hint`, `required`, `disabled`, `placeholder`, `options`, `rows`
- `loader`: `label`, `size`
- `modal`: `name`, `title`, `maxWidth`
- `nav-item`: `href`, `icon`, `active`, `badge`, `disabled`, `sub`
- `page-header`: `title`, `subtitle`, `breadcrumbs`; optional `actions` slot
- `stat`: `label`, `value`, `icon`, `tone`, `hint`, `href`, `compact`. For primary operational dashboards and major overview headers, use the standardized **3-Zone KPI Card Pattern** (see below) for rich visual hierarchy and contextual grounding.
- `table`: `stickyHeader`, `zebra`, composed with `table.head`, `table.row`, `table.th`, `table.td` (`align`, `numeric`, `muted`), and `table.empty`

Inspect the component source before using it because contracts may evolve. Do not pass invented props. If a shared component almost fits, extend it only when multiple real consumers benefit and existing uses remain compatible; otherwise keep the local exception in the page.

## Density, Hierarchy, and Visual Restraint

HIMS screens are operational tools first. Favor dense, scannable, hierarchical layouts over promotional or decorative treatment. Everything below is a requirement, not a preference.

### Screen Space Maximization: Full-Width Layouts & Horizontal Expansion ("Bawal Tipirin ang Space")

Operational hospital workspaces, clinical catalogs, inventory tables, procurement suites, and analytics dashboards must **aggressively maximize screen real estate across the entire display**. Never waste side space with massive empty gutters while forcing content down into endless vertical scrolling ("bawal tipirin ang space; anlaki ng space sa gilid na kung gagamitin puwede pa malagay yung mga nasa ilalim").

- **Full-Width by Default (`max-w-none`)**:
  - The shared application layout (`<x-app-layout>`) is configured by default to `fullWidth = true` (`max-w-none` inside `resources/views/layouts/app.blade.php`), stretching content edge-to-edge with standard responsive horizontal padding (`px-4 sm:px-6 lg:px-8`).
  - **Eliminate the "Wasted Side Gutter Flaw":** Wasting 200px–500px of empty space on the left and right borders of widescreen/desktop displays (1080p, 1440p, 4K) is strictly forbidden. Confining complex operational interfaces to an artificially narrow strip (such as unconfigured `max-w-7xl`) cramps multi-column tables, squishes forms, and destroys data density.
  - Every operational interface must breathe across the full display width so users can view data density, columns, and metrics comfortably without artificial truncation.

- **Utilizing Horizontal Space to Pull Up Vertical Overflow ("I-akyat ang mga Nasa Ilalim")**:
  - When horizontal space is maximized, **use that extra width to position companion panels, forms, filters, and queues side-by-side rather than stacking them vertically down the page**:
    - **Split-Pane Workspaces (Forms + Pipeline/Table)**: Instead of putting an action form (e.g. "Prepare Purchase Order") on top of or beneath a pipeline, use responsive multi-column grid tracks:
      ```html
      <!-- Side-by-side layout utilizes full width so the form and table coexist without vertical scrolling -->
      <div class="grid items-start gap-4 lg:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.65fr)] xl:grid-cols-[minmax(22rem,0.75fr)_minmax(0,2fr)]">
          <section><!-- Action / Input Form Panel --></section>
          <section><!-- Primary Table / Pipeline List --></section>
      </div>
      ```
    - **Single-Line Toolbars**: Filter controls, date pickers, status dropdowns, search inputs, and action buttons must expand horizontally across the top of the table in a single cohesive flex-wrap bar, rather than breaking into multiple vertical rows.
    - **Multi-Zone Dashboards**: Elevate supplementary summary stats, KPI cards, and secondary review panels into horizontal 3-column, 4-column, or 5-column grids (`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4`) so that vital metrics sit above the fold without pushing data tables down.
  - **Core Rule**: If a user has to scroll past an empty vertical expanse while large horizontal margins sit idle on the screen edges, the layout is broken. Fill the horizontal real estate to bring critical controls and lists up into view.

- **Table Column Breathing Room**:
  - Full-width real estate must be leveraged to give tables wide, comfortable columns. Essential columns (Code/SKU, Item Name, Category, Stock on Hand, Reorder Level, Cost/Price, Supplier, Status, and Row Actions) must have generous column widths (`min-w-[...]`) with ample breathing room so no text or numbers are clipped or crammed.

- **When is Boxed (`max-w-7xl` or narrower) Permitted?**:
  - ONLY for focused, single-column prose reading views (e.g. Terms of Service, Privacy Policy), narrow profile security forms, or login dialogs where lines of continuous text would otherwise exceed comfortable reading length (>80 characters). All operational workspaces, catalogs, tables, and dashboards MUST use full-width.

### Banners

`x-ui.alert` is the only page-level notice primitive. Do not turn it into decoration.

- Reserve alerts for state the user must act on or cannot infer: validation failure, blocked operation, expired session, denied permission.
- One banner per region. Do not stack an info banner, a success banner, and a warning banner above the same page; consolidate them into a single alert with a `title` and body copy, or move the content inline to the element it describes.
- No welcome, marketing, "did you know", celebratory, or reassurance banners. Do not re-announce on every load what the page header already states.
- A normal empty state is not a banner; use `table.empty` or an in-card empty block.
- Keep the `page-header` `subtitle` to one line of orientation. A paragraph of prose there is a banner by another name.
- Route flash messages through the existing central flash rendering. Do not add a second banner partial or a page-local duplicate.
- Keep `dismissible` for transient confirmations only. A condition the user still has to resolve must stay visible.

### Avoid Redundant Feedback & Dual Notifications ("Bawal ang Redundant / Isang Success Feedback Lang")

Every authenticated page in HIMS wrapped with `<x-app-layout>` automatically includes the centralized, floating toast notification HUD (`layouts/partials/toast-notifications.blade.php`), which intercepts `session('status')`, `session('success')`, `session('error')`, `session('warning')`, and `session('info')`.

- **Strict Prohibition of Duplicate In-Page Success Alerts**:
  - Never add an in-page `@if (session('success')) <x-ui.alert variant="success">` or page-local green alert banner on screens using `<x-app-layout>`.
  - Doing so creates dual/redundant notifications—the floating toast HUD appears in the top-right corner while an identical green banner renders simultaneously in the main document, cluttering the view, pushing down tables and dashboards, and confusing the user ("kasi redundant na yang mga ganiyan kapag may another na nag-e-exist, make sure na may matitirang isa na success ang action na ginawa").
  - **Only ONE success notification must exist per action**: The centralized floating toast notification HUD serves as the single authority for transient success feedback.
- **When are Page Alerts Permitted?**:
  - In-page alerts (`x-ui.alert`) are reserved strictly for:
    1. Actionable blocking error summaries (`@if ($errors->any()) <x-ui.alert variant="danger">`), where the user must review specific form fields or system rejections.
    2. Persistent operational warnings or compliance states (e.g. "Archived Supplier Record", "Not eligible for procurement").
  - Never use an in-page banner for routine success confirmations that the central toast already handles.

### Avoid Redundant Loading Indicators ("Bawal ang Dalawang Loading / Isang Loading Indicator Lang")

Never show multiple loading indicators simultaneously for a single user action. Redundant loading states—such as an in-button spinner running simultaneously with a center-screen modal overlay card—create visual noise, distract the user, and obscure form content ("pati sa pag-loading, hindi rin dapat may redundant, tulad niyan dalawa ang nag-lo-loading, itira ang much better na loading or yung mas akma, sa login page, mas gusto ko yung nag-lo-loading sa mismong button kaysa doon sa nasa gitna na may HIMS na nakalagay").

- **Form Submissions with Action Buttons (Login, Modals, Edit/Create Forms, Lifecycle Actions)**:
  - **In-Button Loading State as the Standard**: When a form is submitted via an action button, use the in-button loader (`setButtonLoading` with `data-loading-text="..."` e.g., "Signing in...", "Saving...", "Archiving..."). The button is automatically disabled to prevent double submissions and displays an inline micro-spinner and contextual text.
  - **Prohibition of Center Screen Overlay on Button Submissions**: Never trigger the central screen overlay (`data-hims-loading-overlay` card saying "Please wait while HIMS processes your request") when an in-button loader is already active. The button itself is the much cleaner, more contextual, and superior feedback mechanism.
- **When is the Central Loading Overlay Permitted?**:
  - The central screen overlay is strictly reserved for actions where no contextual button exists:
    1. Full-page internal link navigation (`a[href]` clicks) where navigation progress needs visibility ("Loading page...").
    2. File export/download operations via `[data-hims-download]` ("Preparing document...").
    3. Headless or programmatic form submissions where no submitter button exists ("Processing request...").

### No emoji

- No emoji in labels, headings, buttons, badges, table cells, flash copy, validation messages, notifications, or option text.
- Use `x-ui.icon` with a real icon name instead. The name list is in `resources/views/components/ui/icon.blade.php`.
- Icon-only controls still need an accessible name; decorative icons stay `aria-hidden`.
- Known violation to fix whenever that file is touched: the `⭐` in the report-type select at `resources/views/inventory/reports/index.blade.php:396`.

### Badges

- Use `x-ui.badge` and pass the domain `status` string, letting the component map it to the semantic palette. Do not hand-write badge markup or choose a colour in the page.
- If a status is missing from the map in `resources/views/components/ui/badge.blade.php`, add it there so every screen colours it identically. Do not pass an ad-hoc `variant` at one call site.
- Convey one state per field. Do not badge a value that already states the same thing, and never use a badge as decoration or as a button.
- `dot` is a second channel for the same information; use it only where the badge is dense enough to need reinforcement, and keep the text label. Never rely on colour or dot alone.
- Keep labels to a word or two. The component is `whitespace-nowrap`, so a long label widens its column and is a common cause of table overflow.
- Use plain `tabular-nums` text for counts. Reserve colour for state, not magnitude.

### Strict Badge Restraint: Avoid Excessive, Redundant & Decorative Badges ("Bawal ang Sobrang Badge / Iwasan ang Paggawa ng Badge")

Do not litter the user interface with badges. Excessive badges create visual fatigue, noise, and clutter, making it difficult to spot genuine operational statuses ("ayaw ko ng masyadong maraming badge kaya kung maari iwasan na huwag gumawa ng badge").

- **Never Use Badges as Visual Decoration**:
  - Never add redundant status pills, action chips, or decorative labels on stat cards, KPI card footers, cards, or page headers (such as `Catalog active`, `Replenish`, `Action required`, `Asset valuation`, `Investigate`, `Pipeline active`, `In transit`, `Urgent`, `DOA Queue`, `Cleared`).
  - Zone 3 KPI card footers must use plain, unadorned text (`text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate`) for grounding details. Do NOT append decorative pill badges or chips in card footers.
- **Strictly Limited to Essential Entity Lifecycle Status**:
  - Badges are strictly reserved for core lifecycle status values in data tables and resource detail screens where instant visual classification of an entity state is required (e.g. `status` in table rows like `Pending`, `Approved`, `Dispatched`, `Delivered`, `Archived`).
  - Convey only ONE state per field. If the metric or label already explains the condition (e.g., "Needs reorder", "Open alerts"), do not attach another badge that merely repeats the concept.
- **Prefer Plain Typography**:
  - Use clear, subtle text labels, tabular numbers, or simple dot indicators instead of wrapping every secondary string in a colored pill container.

### Standardized Operational KPI & Stat Metric Cards

All primary operational dashboards (such as Demand Forecasting, Executive Dashboard, Inventory Overview, and Procurement Summaries) must use the standardized **3-Zone KPI Card Pattern** to ensure strict visual consistency, dark-mode readability, and high data density.

#### 1. Three-Zone Card Architecture
Every KPI card is structured in a vertically balanced flex container (`flex flex-col justify-between`):

- **Zone 1: Header (Identity & Thematic Anchor)**
  - **Left**: Category or metric title in bold uppercase (`text-xs sm:text-sm font-bold uppercase tracking-wider text-<tone>-700 dark:text-<tone>-300`).
  - **Right**: Distinct themed icon badge (`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-<tone>-100 text-<tone>-700 dark:bg-<tone>-950/80 dark:text-<tone>-300 ring-1 ring-<tone>-200 dark:ring-<tone>-800/50`).
- **Zone 2: Primary Value Display (Visual Anchor)**
  - **Dominant Metric**: Large, high-contrast number formatted with `tabular-nums` (`text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-neutral-950 dark:text-white` or semantic tone color). Never use small or faint numbers.
  - **Unit Suffix**: Inline unit suffix with baseline alignment (`text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400` or matching tone color).
  - **No State Pills / Status Badges**: Strictly avoid placing badges or status pills (e.g. `ACTIVE`, `OPEN`, `OUT OF STOCK`, `HIGH`, `LOW`) inside Zone 2. Let the metric, its color, and its label speak for itself without redundant badge clutter.
- **Zone 3: Contextual Footer (Grounding Details & Clean Context)**
  - Separated by a subtle divider: `mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80`.
  - **Grounding Explanation**: Clean, unadorned typography (`text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate`).
  - **No Decorative Badges / Chips**: Never append pill badges or status tags to the footer. Keep the grounding footer simple and badge-free.

#### 2. Standard Container Markup
```blade
<div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
    {{-- Zone 1: Header --}}
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">Predicted Demand</p>
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/80 dark:text-violet-300 ring-1 ring-violet-200 dark:ring-violet-800/50">
            <x-ui.icon name="sparkles" class="h-5 w-5" />
        </span>
    </div>
    {{-- Zone 2: Value --}}
    <div class="mt-3 flex items-baseline gap-1.5">
        <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-violet-700 dark:text-violet-300">1,181</span>
        <span class="text-sm sm:text-base font-bold text-violet-600/80 dark:text-violet-400/80">units</span>
    </div>
    {{-- Zone 3: Footer --}}
    <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
        <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">~39.4 units / day (30d horizon)</span>
    </div>
</div>
```

#### 3. Semantic Palette Guidelines
- **Neutral / Stock on Hand**: `text-neutral-600 dark:text-neutral-300`, icon badge in `bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200`.
- **Violet / AI & Forecasts**: `text-violet-700 dark:text-violet-300`, icon badge in `bg-violet-100 text-violet-700 dark:bg-violet-950/80 dark:text-violet-300`.
- **Primary / Procurement & Actions**: `text-primary-700 dark:text-primary-300`, icon badge in `bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300`.
- **Rose / Critical Shortages & Risk**: `text-rose-700 dark:text-rose-300`, value in `text-rose-600 dark:text-rose-400`, badge in `bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300`.
- **Emerald / System Health & Confidence**: `text-emerald-700 dark:text-emerald-300`, value in `text-emerald-600 dark:text-emerald-400`, badge in `bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300`.

#### 4. Grid Alignment Rule
Place KPI cards in responsive CSS grids (`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` or `lg:grid-cols-5 gap-3.5 sm:gap-4`). Because every card enforces `flex flex-col justify-between`, all footers and dividers align across the entire horizontal row regardless of value differences or label wrapping.

### Compact and hierarchical

- Prefer the compact form when a page repeats a component: `stat` has `compact`, `button` has `size="sm"`, table cells accept `muted`/`numeric`. Keep one density per row or card rather than mixing.
- Express structure through hierarchy, not repetition: `nav-item` with `sub` for nested navigation, `card` `title`/`subtitle` with its `actions`/`footer` slots, `page-header` `breadcrumbs`. Do not flatten a hierarchy into sibling cards, and do not nest cards inside cards.
- Put detail behind a disclosure the stack already supports — the shared modal, an expandable row, or the detail route. A summary line plus a detail view beats a wall of inline key-value pairs.
- Reuse the established spacing and type scale. Do not add spacing steps, custom font sizes, or per-page padding to make a layout fit.

### Strict Zero Horizontal Scrollbar & Complete Responsiveness

A horizontal scrollbar is an unacceptable layout failure on any screen size. Users must never be forced to scroll horizontally to read data, navigate, or interact with controls. Every page, card, grid, table, and toolbar must be **100% responsive** from narrow mobile (320px) to ultra-wide desktop (1920px+).

Because `main` is `overflow-x-clip`, horizontal overflow gets trapped and cuts off content or introduces unsightly nested scrollbars inside cards and tables.

- **Responsive Table Architecture (Progressive Disclosure)**:
  - The `x-ui.table` shell provides `overflow-x-auto` strictly as an emergency safeguard, never as a design crutch. Size and adapt the table so that it naturally fits without scrolling.
  - Progressively hide non-essential secondary columns on smaller viewports using Tailwind responsive visibility:
    - Mobile (`<640px`): Show only core identity (Item name / SKU), primary status badge, and primary action. Hide historical metrics, dates, and secondary categories (`hidden sm:table-cell`).
    - Tablet (`<1024px`): Hide supplementary metadata like notes or extended timestamps (`hidden lg:table-cell`).
    - Desktop (`>=1024px`): Display the full multi-column data view.
  - Never hide the unique identifier or the primary action needed to interact with a row.
  - Trim table padding across breakpoints (`px-2 sm:px-3 lg:px-4`) so columns fit cleanly.

- **Responsive Toolbars, Filters & Controls**:
  - Filter bars, action button rows, and scope selectors must always wrap:
    ```html
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between flex-wrap">
    ```
  - Inputs and select dropdowns must stretch on mobile and size cleanly on desktop (`w-full sm:w-auto` or responsive grids `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5`).
  - Never place multiple fixed-width inputs next to each other on a single row without `flex-wrap` or responsive grid breakpoints.

- **Flex Child Width Traps (`min-w-0`)**:
  - By default in CSS, flex items have `min-width: auto`. Any long unbroken string (SKU, email address, document title, audit hash, URL) will push the flex container past the viewport edge and cause horizontal overflow.
  - **Always apply `min-w-0`** to flex children containing text or badges, combined with `truncate` (and `title="..."` for accessibility) or `break-words`.

- **Responsive Grid Stepping**:
  - Never use fixed columns without responsive step-downs:
    ```html
    <!-- Correct: Gracefully stacks from 1 column up to 5 -->
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
    ```
  - Never define fixed pixel widths on cards, containers, or wrappers (e.g. `w-[900px]`); use fluid sizing (`w-full`, `max-w-none`, percentages, or grid tracks `minmax(0, 1fr)`).

- **Verification Across Breakpoints**:
  - Every UI edit must be verified at narrow mobile (375px), intermediate tablet (768px), and standard desktop (1280px / 1920px). Confirm that zero horizontal scrollbars appear on `window`, `body`, `main`, or any nested card wrapper.

### Element Sizing, Buffer Space & Text Clipping Prevention ("Laging May Pasobra")

Zero letters, words, or character fragments may ever be clipped, cut off at the boundaries, or covered by adjacent controls or icons ("walang letters dapat na natatabunan"). Components, inputs, and badges must dynamically accommodate the true width of their content plus generous safety buffer ("inaakma ang size ng mga elements/components sa dapat talang size nila, laging may pasobra").

- **Mandatory `<select>` Dropdown Clearance (`pr-8` / `pr-10`)**:
  - The leading cause of clipped text is using symmetrical or narrow padding (such as `px-2` or `px-3`) on `<select>` elements. Browser and Tailwind form styles position the dropdown chevron arrow on the inside right edge (~0.5rem–0.75rem from the edge). When right padding is too tight, the option text runs directly underneath the chevron, clipping the trailing letters (e.g. converting `5 per page` into `5 per pag`).
  - **Strict Requirement**: Every `<select>` control in HIMS must ALWAYS provide generous right clearance dedicated to the arrow:
    - Small / compact filter selects: `pl-2.5 pr-8` (minimum 2rem / 32px right padding).
    - Standard form selects: `pl-3 pr-10` (2.5rem / 40px right padding).
    - Example:
      ```html
      <!-- Correct: Text has ample buffer from the dropdown chevron -->
      <select class="min-h-9 rounded-md border border-neutral-300 pl-2.5 pr-8 text-xs text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
          <option value="5">5 per page</option>
      </select>
      ```
  - **Never use `px-2` or `px-3` on `<select>` elements.** Always use asymmetrical `pl-*` and `pr-8` / `pr-10`.

- **Fluid Widths vs. Fixed Squeezing**:
  - Never assign rigid, artificially narrow fixed widths (e.g., `w-14`, `w-16`, `w-20`, `max-w-[80px]`) to buttons, selects, badges, chips, or inputs containing dynamic labels or localized text.
  - Sizing must comfortably match the longest realistic content plus generous horizontal padding (`px-2.5`, `px-3`, `px-3.5`).
  - When displaying numbers with unit suffixes (e.g. `5 per page`, `1,200 units`, `₱24,500.00 / lot`), ensure the container accommodates both the maximum anticipated digits and the full suffix without squeezing or wrapping.

- **Buffer Space ("Pasobra") on Cards, Pills & Table Cells**:
  - Table cells, status pills, and KPI footers must include sufficient cell padding (`px-3 sm:px-4`) and column width (`min-w-[...]`).
  - Where truncation (`truncate`) is necessary for lengthy unstructured strings (such as product names, audit hashes, or email addresses), always pair with `min-w-0` and an accessible `title="..."` attribute.
  - **Never truncate short operational controls, options, counters, or status badges** where complete legibility is mandatory.

- **Vertical Line-Height & Descender Clearance**:
  - Always pair font sizes with adequate line-height (`leading-normal` or `leading-relaxed`) and vertical padding (`py-1.5` to `py-2.5`).
  - Never choke container heights with tight pixel constraints that clip letter descenders (e.g. `g`, `y`, `p`, `q`, `j`) or uppercase accents.

### Unified Pagination Standards & 20-Button Hard Ceiling

Every paginated table, list, log, and workspace in HIMS must follow the exact same visual design system, and pagination bars must never render an excessive number of page buttons.

- **Unified Standard Layout Across All Screens ("Same style lahat")**:
  - All paginated views must use the shared pagination template at `resources/views/vendor/pagination/tailwind.blade.php` via `$collection->links()` or `$collection->fragment('section')->onEachSide(1)->links()`.
  - Never create page-local custom pagination bars, disparate button shapes, non-standard colors, or ad-hoc button layouts.
  - **Visual Anatomy**:
    - **Results Summary (Left)**: `Showing X to Y of Z items` in `text-xs text-neutral-600 dark:text-neutral-400` with bold neutral numbers (`font-semibold text-neutral-900 dark:text-neutral-100`).
    - **Navigation Button Group (Right)**:
      - Grouped in `flex items-center gap-1 text-xs`.
      - **Active Page Pill**: High-contrast solid pill (`inline-flex min-w-[2rem] items-center justify-center rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-2xs dark:bg-primary-600 dark:text-white`).
      - **Inactive Page Button**: Subtle bordered interactive button (`inline-flex min-w-[2rem] items-center justify-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 transition`).
      - **Previous / Next Buttons**: Bordered button with SVG chevron icon and label, disabling gracefully with `cursor-not-allowed border-neutral-200 bg-neutral-100/60 text-neutral-400 dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-600` when on first or last page.

- **Strict 20-Button Hard Ceiling ("Hanggang 20 buttons lang ang puwede")**:
  - A pagination component must **never render more than 20 page buttons under any circumstance**.
  - Unbounded pagination loops cause catastrophic UI breakage: horizontal overflow, unwanted scrollbars, multi-row button wrapping, and broken card layouts on mobile and desktop viewports.
  - The shared pagination template strictly caps rendered page buttons at 20 (`$renderedButtons > 20`).
  - Paginator callers should configure standard sliding windows (e.g. `->onEachSide(1)`).
  - Any client-side pagination (such as Alpine.js or JavaScript tabular components) must strictly enforce this exact same 20-button ceiling and visual hierarchy.

## Skeleton Loading Design

All HIMS pages and components that load asynchronous data must have a proper loading state when appropriate.

The loading state must clearly communicate that the system is still processing/loading data, rather than making the interface look broken, empty, or unavailable.

### 1. USE SKELETONS FOR DATA LOADING

When content is being fetched or calculated, prefer an animated skeleton that resembles the structure of the final content.

Examples:
- Dashboard cards
- Tables
- Charts
- AI forecast results
- Inventory data
- Supplier lists
- User lists
- Notifications
- Reports
- Detail panels
- KPI sections
- Search results
- Modal content

The skeleton should approximately match the size and layout of the content it will replace.

Do NOT use arbitrary large blank areas.

### 2. NEVER CONFUSE LOADING WITH EMPTY STATE

Loading, empty, and error states must be separate.

- **LOADING**: Show an animated skeleton.
- **EMPTY**: The request completed successfully, but there is genuinely no data.
- **ERROR**: The request failed. Show a useful error state and an appropriate Retry action.
- **SUCCESS**: Show the actual data.

Never display messages such as:
- "No data"
- "Data unavailable"
- "No results"
- "Nothing found"

while the request is still loading.

Do not use an empty-state message as a substitute for a loading state.

### 3. ANIMATED SKELETON

Skeletons should have a subtle animation such as shimmer or pulse.

The animation must:
- Be smooth
- Be subtle
- Match the HIMS visual design
- Avoid distracting the user
- Avoid excessive CPU usage
- Stop when loading finishes
- Respect `prefers-reduced-motion`

For reduced-motion users, use a static skeleton or significantly reduced animation.

### 4. MATCH THE ACTUAL COMPONENT

A skeleton should resemble the final component.

For example:

- **A chart should have**:
  - Chart-area skeleton
  - Axis/label placeholders where appropriate
  - Legend/value placeholders if they exist

- **A KPI card should have**:
  - Label placeholder
  - Value placeholder
  - Supporting text placeholder

- **A table should have**:
  - Header skeleton
  - Multiple row skeletons
  - Appropriate column widths

- **A detail panel should have**:
  - Title placeholder
  - Metadata placeholders
  - Content placeholders
  - Action placeholders where appropriate

Do not use the same generic rectangular skeleton everywhere if it does not represent the final content.

### 5. DARK MODE COMPATIBILITY

Skeletons must work correctly in HIMS dark mode.

Use:
- Dark base surfaces
- Slightly lighter skeleton surfaces
- Subtle borders
- Low-contrast shimmer

Avoid bright white loading blocks.

The skeleton must remain visible without becoming visually aggressive.

Also ensure skeleton styles do not leak incorrectly into light mode.

### 6. PREVENT LAYOUT SHIFT

The loading state should reserve approximately the same amount of space as the final content.

Avoid:
- Page jumping
- Cards changing height dramatically
- Content moving when loading completes
- Unexpected horizontal overflow
- Large vertical expansion
- Sudden modal resizing

The transition should be:
- `LOADING` → `SUCCESS`
or:
- `LOADING` → `EMPTY`
or:
- `LOADING` → `ERROR`

without unnecessary layout movement.

### 7. ASYNC FILTERS AND SEARCH

When users change filters, search terms, selected items, date ranges, or other controls that trigger asynchronous requests:

1. Enter the loading state immediately.
2. Show the appropriate skeleton.
3. Fetch the new data.
4. Replace the skeleton with the correct result.
5. Never present stale data as the result of the new selection.

If appropriate, preserve the existing layout while replacing only the affected content with skeletons.

### 8. PREVENT STALE RESULTS AND RACE CONDITIONS

If multiple requests can happen quickly, ensure an older request cannot overwrite a newer request.

Example:
Item A → Item B → Item C

The UI must ultimately display Item C's result.

Do not allow Item A or Item B to overwrite the latest selection after their requests finish.

Use the existing application architecture/state-management approach where possible.

### 9. NO FAKE DATA DURING LOADING

Skeletons are visual placeholders only.

Do NOT:
- Generate fake numbers
- Randomize KPI values
- Display fake chart data
- Display fake forecast results
- Display fake inventory quantities
- Insert temporary database records

Use real HIMS data once loading finishes.

### 10. LOADING STATES FOR AI FEATURES

AI-powered HIMS features must also use proper loading states.

Examples:
- AI demand forecasting
- AI forecast insights
- AI-generated recommendations
- HIMS AI chatbot responses
- Forecast confidence
- Risk analysis

While AI processing is happening, show an appropriate loading state.

Do not prematurely display:
- "No forecast"
- "No insight"
- "Outside scope"
- "No recommendation"
- Empty result messages

unless the AI request has actually completed and returned that result.

### 11. LOADING STATES IN MODALS

When a modal opens and its content requires asynchronous data:
- Open the modal at the intended size.
- Show a modal-specific skeleton.
- Avoid displaying an empty modal while waiting.
- Avoid unnecessary modal resizing.
- Keep actions disabled only when necessary.
- Replace the skeleton with actual content when ready.

Do not make the entire page reload simply to populate modal content.

### 12. ACCESSIBILITY

Where appropriate, loading containers should expose their state through accessible semantics such as:
- `aria-busy="true"`
- Appropriate status/live-region behavior
- Meaningful screen-reader loading text

Do not repeatedly announce animation changes to screen readers.

When loading finishes, update the accessible state correctly.

### 13. ERROR AND RETRY

If a real request fails:
- Stop the skeleton animation.
- Display a clear error state.
- Explain the problem briefly.
- Provide Retry when appropriate.
- Do not expose raw Laravel/database/debug errors.

Do not automatically classify slow requests as errors.

### 14. PERFORMANCE

Skeleton implementations should be lightweight.

Prefer:
- CSS animations
- Existing component utilities
- Reusable skeleton components

Avoid unnecessary JavaScript animation loops.

Do not introduce heavy dependencies only for skeleton loading.

### 15. REUSABILITY

When multiple pages use similar loading patterns, create reusable components where appropriate.

Examples:
- `SkeletonCard`
- `SkeletonTable`
- `SkeletonChart`
- `SkeletonList`
- `SkeletonDetail`
- `SkeletonModal`

Use the project's existing component architecture instead of creating duplicate implementations.

### 16. VISUAL QUALITY STANDARD

A good skeleton should make the user feel:
"The system is currently loading the information."

It must NOT make the user feel:
"The system is broken."
"There is no data."
"The feature does not work."

Skeleton loading is part of the HIMS UX, not an afterthought.

### 17. FINAL RULE

Whenever implementing a new asynchronous HIMS feature or modifying an existing one, explicitly consider all four states:
1. Loading
2. Success
3. Empty
4. Error

Do not consider a feature complete if it only handles the successful state.

The loading state must be intentional, responsive, accessible, visually consistent with HIMS, and representative of the content that will eventually appear.

### Accurate Skeleton Structure

Skeleton loaders MUST accurately represent the final loaded component.

The skeleton is not a generic placeholder layout.

Before implementing a skeleton:

1. Inspect the actual loaded component.
2. Identify its real sections and hierarchy.
3. Match the skeleton to those sections.
4. Match approximate dimensions and spacing.
5. Match the responsive layout.
6. Verify the loading state against the final loaded state.

The skeleton must maintain visual continuity between:

LOADING → LOADED

### Shape Accuracy Rule

Every skeleton placeholder should correspond to an actual final UI element.

Do not add:

- Generic boxes
- Arbitrary cards
- Placeholder sections that do not exist
- Incorrect numbers of containers
- Incorrect grid structures
- Incorrect chart dimensions
- Oversized or undersized placeholders

If the final component has a chart, the skeleton should resemble a chart.

If the final component has KPI cards, the skeleton should resemble KPI cards.

If the final component has a sidebar, the skeleton should preserve the sidebar structure.

### Before-and-After Validation

When implementing or modifying a skeleton, compare:

1. Loading state
2. Loaded state

The skeleton should be evaluated based on structural similarity, not merely whether it looks visually polished.

The final content should feel like it is replacing the skeleton rather than replacing an entirely different layout.

### Responsive Skeleton Accuracy

Skeleton structure must also match the final component at each responsive breakpoint.

Do not design the skeleton only for desktop while the actual component uses a different mobile/tablet structure.

### No Generic Skeleton Shortcut

Never use generic repeated rectangles as a shortcut when the final UI has a known structure.

Use component-specific skeletons that accurately reflect the actual UI.

## Preserve Existing Interaction Contracts

- Forms and internal navigation participate in the global loading system in `resources/js/app.js`. Use established `data-loading-text` and opt-out hooks rather than adding a second spinner system. Loading behavior also guards against duplicate submissions.
- Confirmation dialogs use the shared decision-confirmation partial and `data-confirm-*` hooks, with specialized handling for MFA and email changes. Do not add a competing modal without checking this flow.
- Flash feedback is rendered centrally by the floating Toast Notification HUD in `layouts/partials/toast-notifications.blade.php` for `status`/`success`, `error`, `warning`, and `info`. Never create duplicate in-page success alert banners for messages already captured by the central toast. Profile/auth pages maintain local named error bags for inline form validation errors.
- The session-warning UI reflects server state; client timers must not become the authority for session validity.
- Dashboard and audit autocomplete interactions already use Alpine/JavaScript contracts. Preserve cancellation, loading, empty, error, and stale-request behavior.
- Keep route names, breadcrumbs, sidebar active states, back/redirect behavior, and permission-based visibility consistent.

## Required UI States

Implement only the states relevant to the feature, but do not omit realistic ones:

- initial/loading and duplicate-submit protection;
- populated and empty content;
- field validation and operation failure;
- success feedback;
- disabled/read-only/unauthorized state when the domain supports it;
- expired or stale state for session/MFA flows.

Frontend restrictions never replace server validation or authorization. Use `hims-security-auth` for security-sensitive UI.

## Accessibility and Responsive Behavior

- Keep semantic headings, landmarks, tables, form labels, button/link semantics, and meaningful document order.
- Associate hints/errors with fields and preserve visible focus indicators.
- Give icon-only controls an accessible name; do not add redundant names to decorative icons already hidden from assistive technology.
- Ensure dialogs have an accessible name, keyboard dismissal/focus behavior, and non-mouse operation. Reuse the existing modal/confirmation implementation where possible.
- Do not convey status by color alone. Use semantic text/badges and sufficient contrast.
- Respect `prefers-reduced-motion`; avoid decorative animation that competes with operational data.
- Check narrow mobile, intermediate, and desktop layouts. Stack and reduce columns instead of scrolling sideways, and verify no horizontal bar appears; do not hide critical actions or the data needed to identify a row to make a layout fit.

## Verification and Failure Handling

- For copy-only changes, render the affected route and verify the text/context.
- For Blade/component changes, run the nearest feature tests and inspect authenticated/guest plus permitted/forbidden states as applicable.
- For JavaScript, CSS, Tailwind, or Vite changes, run `npm run build` and exercise the interaction, including cancel, failure, repeated click, and keyboard paths.
- A Blade `assertSee` check proves rendered output, not full browser behavior. Use visual/manual interaction checks when behavior or responsive layout changed.
- When layout changed, resize through narrow, intermediate, and wide widths and confirm no horizontal scrollbar appears on `main`, on a table shell, or on a card. Check emoji and banner count in the rendered output too, since both are easy to reintroduce.
- If a UI flow fails, inspect the response, validation bag, console/build error, and hook/component contract. Do not hide the exception, force a success message, disable server checks, or remove error handling.
- Use `hims-testing` for test scope and report what was actually verified.
