---
name: hims-ui-ux
description: Guides HIMS UI and UX changes involving Blade, Tailwind CSS, Alpine.js, layouts, responsive pages, forms, tables, modals, notifications, loading states, navigation, accessibility, or frontend behavior. Use when modifying existing interfaces or adding screens that must match the HIMS design system and interaction contracts.
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

- `alert`: `variant`, `title`, `dismissible`
- `badge`: `status`, `variant`, `dot`
- `button`: `variant`, `size`, `href`, `type`, `icon`
- `card`: `title`, `subtitle`, `padding`; optional `header`, `actions`, and `footer` slots
- `field`: `name`, `label`, `type`, `value`, `hint`, `required`, `disabled`, `placeholder`, `options`, `rows`
- `loader`: `label`, `size`
- `modal`: `name`, `title`, `maxWidth`
- `nav-item`: `href`, `icon`, `active`, `badge`, `disabled`
- `page-header`: `title`, `subtitle`, `breadcrumbs`; optional `actions` slot
- `stat`: `label`, `value`, `icon`, `tone`, `hint`, `href`
- `table`: `stickyHeader`, `zebra`, composed with `table.head`, `table.row`, `table.th`, `table.td`, and `table.empty`

Inspect the component source before using it because contracts may evolve. Do not pass invented props. If a shared component almost fits, extend it only when multiple real consumers benefit and existing uses remain compatible; otherwise keep the local exception in the page.

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
- Check narrow mobile, intermediate, and desktop layouts. Use responsive stacking and existing horizontally scrollable table behavior; do not hide critical actions or data to make a layout fit.

## Verification and Failure Handling

- For copy-only changes, render the affected route and verify the text/context.
- For Blade/component changes, run the nearest feature tests and inspect authenticated/guest plus permitted/forbidden states as applicable.
- For JavaScript, CSS, Tailwind, or Vite changes, run `npm run build` and exercise the interaction, including cancel, failure, repeated click, and keyboard paths.
- A Blade `assertSee` check proves rendered output, not full browser behavior. Use visual/manual interaction checks when behavior or responsive layout changed.
- If a UI flow fails, inspect the response, validation bag, console/build error, and hook/component contract. Do not hide the exception, force a success message, disable server checks, or remove error handling.
- Use `hims-testing` for test scope and report what was actually verified.
