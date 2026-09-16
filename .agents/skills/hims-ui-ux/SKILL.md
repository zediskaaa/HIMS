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
- `stat`: `label`, `value`, `icon`, `tone`, `hint`, `href`, `compact`
- `table`: `stickyHeader`, `zebra`, composed with `table.head`, `table.row`, `table.th`, `table.td` (`align`, `numeric`, `muted`), and `table.empty`

Inspect the component source before using it because contracts may evolve. Do not pass invented props. If a shared component almost fits, extend it only when multiple real consumers benefit and existing uses remain compatible; otherwise keep the local exception in the page.

## Density, Hierarchy, and Visual Restraint

HIMS screens are operational tools first. Favor dense, scannable, hierarchical layouts over promotional or decorative treatment. Everything below is a requirement, not a preference.

### Banners

`x-ui.alert` is the only page-level notice primitive. Do not turn it into decoration.

- Reserve alerts for state the user must act on or cannot infer: validation failure, blocked operation, expired session, denied permission.
- One banner per region. Do not stack an info banner, a success banner, and a warning banner above the same page; consolidate them into a single alert with a `title` and body copy, or move the content inline to the element it describes.
- No welcome, marketing, "did you know", celebratory, or reassurance banners. Do not re-announce on every load what the page header already states.
- A normal empty state is not a banner; use `table.empty` or an in-card empty block.
- Keep the `page-header` `subtitle` to one line of orientation. A paragraph of prose there is a banner by another name.
- Route flash messages through the existing central flash rendering. Do not add a second banner partial or a page-local duplicate.
- Keep `dismissible` for transient confirmations only. A condition the user still has to resolve must stay visible.

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

### Compact and hierarchical

- Prefer the compact form when a page repeats a component: `stat` has `compact`, `button` has `size="sm"`, table cells accept `muted`/`numeric`. Keep one density per row or card rather than mixing.
- Express structure through hierarchy, not repetition: `nav-item` with `sub` for nested navigation, `card` `title`/`subtitle` with its `actions`/`footer` slots, `page-header` `breadcrumbs`. Do not flatten a hierarchy into sibling cards, and do not nest cards inside cards.
- Put detail behind a disclosure the stack already supports — the shared modal, an expandable row, or the detail route. A summary line plus a detail view beats a wall of inline key-value pairs.
- Reuse the established spacing and type scale. Do not add spacing steps, custom font sizes, or per-page padding to make a layout fit.

### No horizontal scrollbar

A horizontal bar is a layout failure, not a feature. `main` is `overflow-x-clip`, so overflow is trapped inside whichever component causes it — usually a table or a flex row that never wraps.

- The `x-ui.table` shell ships `overflow-x-auto` as a fallback, never as the layout strategy. Size the table so it fits its container at every breakpoint.
- Drop non-essential columns as width shrinks with `hidden`/`sm:table-cell`/`md:table-cell`/`lg:table-cell`, keeping identity, status, and the primary action visible. Never hide the only way to act on a row, or the data needed to tell rows apart.
- Trim cell padding before letting a table overflow. The supplier directory is the precedent: `resources/css/app.css` narrows `.supplier-directory-table` padding and turns the shell's horizontal overflow off for it. Only suppress the scrollbar once the content provably fits — suppressing it while content still overflows hides data instead of fixing it.
- Wrap long unbroken values — `min-w-0` on the flex child, then `truncate` with a `title`, or `break-words`. Unwrapped IDs, emails, and filenames are the usual cause.
- Let control rows wrap with `flex-wrap` and stack at narrow widths instead of scrolling sideways.
- Verify at narrow mobile width with the sidebar, decision-summary aside, and any filter bar present, since those narrow the content column far more than the viewport suggests.

## Preserve Existing Interaction Contracts

- Forms and internal navigation participate in the global loading system in `resources/js/app.js`. Use established `data-loading-text` and opt-out hooks rather than adding a second spinner system. Loading behavior also guards against duplicate submissions.
- Confirmation dialogs use the shared decision-confirmation partial and `data-confirm-*` hooks, with specialized handling for MFA and email changes. Do not add a competing modal without checking this flow.
- Flash feedback is rendered centrally for `status`/`success`, `error`, and `info`; profile/auth pages also have local named error bags and messages. Keep redirects and message keys aligned with the controller.
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
