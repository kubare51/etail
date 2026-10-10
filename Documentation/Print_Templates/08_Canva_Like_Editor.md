# Canva-like Editor Documentation (V2 Konva Designer)

> This document covers the **V2 designer** (`print-templates/designer-v2/{id}`) — a freeform, absolute-position drag-and-drop canvas, which is the closest thing in this codebase to a "Canva-like" editor. The **V1 designer** (`print-templates/designer/{id}`) is a different, block/section-based editor (stack of configurable content blocks like "Company Header", "Items Table", with an embedded GrapesJS sub-modal for authoring custom block HTML) — it is not freeform-canvas-based and is covered briefly at the end of this document for contrast.

## Stack

- **Konva.js 9.x**, loaded from `unpkg.com/konva@9/konva.min.js` (major version pinned only; no exact patch version stated anywhere).
- Plain vanilla ES5 JavaScript in `admin/assets/js/print-designer/konva-designer.js` (~4,900 lines), wrapped in a single IIFE. **No jQuery, Bootstrap JS, or Select2** on this page — a deliberate departure from the rest of the admin app, which otherwise uses jQuery/Bootstrap 3/Select2 per this codebase's standard stack.
- Google Fonts "Inter" and Font Awesome 4.3.0 for UI chrome; `konva-designer.css` for editor styling.

## Canvas model

- One `Konva.Stage` bound to `#kdCanvasContainer`.
- One content `Konva.Layer` ("layer") holding every user element, flat (no per-type sub-layers).
- One `Konva.Layer({listening:false})` ("guideLayer") stacked on top, used only for non-interactive visuals: snap guides, rulers, margin/zone overlays, the rubber-band selection rectangle, and page-break dashed lines. **Only the content layer is saved** — the guide layer is excluded from the serialized design.
- One shared `Konva.Transformer` (rotation disabled) whose `.nodes()` points at whatever is currently selected.
- Paper size is a fixed mm→px lookup table (A4, A3, A5, Letter, 48/53/58/80mm thermal) at a constant `PX_PER_MM = 3.7795`; changing paper size/orientation destroys and recreates the stage.

## Supported elements

| Element | Sidebar label | `customType` |
|---|---|---|
| Text | Text | *(none — plain Konva.Text)* |
| Heading | Heading | *(none — Konva.Text with bold/24px preset)* |
| Image | Image | *(none — plain Konva.Image; uploaded via `FileReader` as a base64 data URI, no server upload endpoint)* |
| Rectangle | Rectangle | *(none — plain Konva.Rect)* |
| Line | Line | *(none — plain Konva.Line, with a wide invisible hit-area so thin lines stay clickable)* |
| Dynamic Table | Dyn Table | `data-table` |
| Static Table | Static Tbl | `static-table` |
| Spacer | Spacer | `spacer` |
| QR Code | QR Code | `qrcode` (draws a decorative placeholder graphic on canvas — **not a real scannable QR**; the real QR bitmap is generated server-side at print time from the `imageSrc: 'qr_code_base64'` sentinel) |

**Dynamic tables** support 18 semantic presets selectable in the table modal: Sales Items, Old Gold, Stone Details, Exchange, Karigar, Tax Summary, Advance Amount, Order Advance, Chit Adjustment, Receipt Adjustment, Order Delivery, Repair Order, Credit Collection, Chit Pre-Close, Purchase, Tax Detail Breakdown, Payment, Payment Methods, and Custom. Each preset maps to a specific data array the server populates at render time (`_konva_get_table_items()` in `konva_receipt_helper.php`). **Static tables** use the same column model but a fixed number of body rows instead of a data-driven loop — useful for a form-style layout rather than a repeating list.

## How elements get onto the canvas

- **Drag-and-drop** from the sidebar (most element types) — the drop handler computes canvas coordinates (accounting for zoom/margin offset), runs a soft collision-avoidance pass to avoid stacking directly on existing content, then dispatches to the relevant `add*()` creator function.
- **Click** (for `image` and `qrcode`, which don't have a meaningful drag target — image opens a file picker, QR is generated programmatically).

## Selection & editing capabilities

- **Single-select** and **shift-click multi-select**.
- **Rubber-band/marquee select** with auto-scroll near the viewport edge while dragging the selection box — implemented with window-level (not stage-level) mouse listeners so selection tracks even when the cursor leaves the canvas.
- **Group drag** — dragging one node in a multi-selection moves the rest in lockstep.
- **Figma-style snap guides** — snaps to canvas center/edges and other elements' edges/centers within a 6px threshold, drawing a red dashed guide line + "CENTER" label; actually adjusts the dragged node's position, not just a visual hint.
- **Rulers** (top/left, with crosshair indicators tracking the selected element) whose tick density adapts to zoom level.
- **Undo/redo** — a plain array-based full-state JSON snapshot stack (max 50 entries), not a diff/command pattern. Every mutating action explicitly calls a "record history" step; it is not automatically hooked into every property setter. `Ctrl+Z`/`Ctrl+Y` (note: no `Ctrl+Shift+Z` redo alternative).
- **Copy/paste/duplicate** — clipboard persists across page reloads via `localStorage` (12-hour expiry, scoped by user id). Pasting warns (non-blocking toast) if a pasted element references a `{{variable}}`, condition, or table field name that doesn't exist in the currently-loaded variable registry — it does not block the paste.
- **Right-click context menu** (Copy/Paste/Duplicate/Delete) and standard keyboard shortcuts (Delete, Ctrl+S save, Ctrl+Z/Y undo/redo, Ctrl+C/V/D copy/paste/duplicate, Ctrl+A select all, Escape deselect).
- **Inline text editing** — double-click a text element overlays a floating `<textarea>` positioned/styled to match the Konva text's font/size/color, committing on blur/Escape.

## Element properties panel

Per-element configuration surfaced when selected, backed by custom Konva node attributes:

| Attribute | Controls |
|---|---|
| `repeatEveryPage` | Element reprints on every page (for header/footer-style content) |
| `conditionVar` | Print-time visibility expression — a single variable, or an OR (`\|\|`)/AND (`&&`) expression (not both mixed together in one expression) |
| `decimalMode` | Overrides decimal-place formatting for a numeric value, per text node or per table column |
| `textCase` | none / uppercase / lowercase / capitalize display transform |
| `tableConfig` | Full column/styling configuration for dynamic and static tables (columns, header/body/footer styling, sub-rows, borders, per-column lock/hide flags) |
| `qrUrlPath` | For QR elements, the URL path segment used to build the print-time QR target |

## Margins & zones

- **Margins** (top/right/bottom/left, mm) are a real content offset, not just a visual guide — the content layer is physically translated by the margin amount, so the canvas is genuinely WYSIWYG. Set via a floating popover with numeric mm inputs.
- **Zones** (header/footer height, mm) mark bands meant to hold repeating content. Important nuance: the zone bands are a **visual reference only** — whether an element actually repeats on every page is controlled purely by that element's `repeatEveryPage` attribute, set manually per-element in the properties panel. Placing an element inside the visual header/footer zone band does **not** automatically make it repeat.

## Multi-page handling (client side)

- **Auto-extend canvas height** — the stage/container grows to fit content after nearly every mutating action, but never shrinks below one page height.
- **Page-break guide lines** — red dashed lines with "Page N" labels drawn every one-page-height interval, purely a visual editor aid. The client does **not** split elements or reflow tables across pages itself — actual print-time pagination (table chunking, page placement) is entirely a server-side concern, handled by `render_konva_template()` (see [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md)).

## How saved designs are stored and re-rendered

- **Save** (`saveDesign()`, `Ctrl+S` or the Save button) serializes only the content layer (`layer.toObject()`, guide layer excluded), strips the live margin offset so the renderer can reapply it independently, and POSTs `{konva_json, paper_size, orientation, margins, zones}` to `print-templates/save-design-v2/{id}`. Per the versioning model documented in [06_Update_Rules.md](06_Update_Rules.md), this always creates a new template row rather than updating in place.
- **Load** (`loadDesign()`) fetches from `print-templates/load-design/{id}`, recreates every node via `Konva.Node.create()`, and rehydrates type-specific state (tables rebuilt from `tableConfig`, images reconstructed from `imageSrc`, text wrap/ellipsis restored — Konva's default serializer was found to drop these, so they're stashed in temporary attrs around save/load specifically to survive it).
- **Re-rendering for print** happens entirely server-side in `render_konva_template()` (`konva_receipt_helper.php`) — it is a from-scratch reconstruction of HTML from the saved JSON node graph plus live bill data, **not** a canvas image/bitmap export. This is the architectural fact behind the "dual-engine drift" risk described in [02_Old_vs_New_Comparison.md](02_Old_vs_New_Comparison.md) and [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md).

## Known editor limitations

- **No validation before save** — an empty canvas, elements placed outside the page bounds, or heavily overlapping elements are all saved without warning. The only required-field validation anywhere in the editor is in the Computed Variable modal (variable name and formula must be non-empty).
- **No canvas-bounds clamp during drag** — only the *initial drop position* of a new element is clamped to stay roughly on-page; once placed, an element can be dragged anywhere, including off-canvas or to negative coordinates, and will save as-is.
- **QR codes are decorative placeholders in the editor**, not real/scannable — the actual QR bitmap only exists at print time.
- **A "Smart Reflow" auto-layout system exists in the code but has been deliberately disabled** at every call site (drag, delete, save, load) via commented-out calls, each annotated with the same explanation: it was "destroying manual positioning and multi-column layouts" (e.g. invoices with left/right detail columns plus a centered QR code) by force-stacking rows with fixed gaps. The row-grouping logic is still defined in the file as dead/vestigial code — a future maintainer should not assume auto-reflow is active just because the functions exist.
- **Two duplicate/shadowing function definitions** were found in the file (`getSampleData` defined twice, the second silently overriding the first per normal JS hoisting) — likely refactor leftovers, worth cleanup but not currently causing an observed bug.

## For contrast: the V1 block editor

`print-templates/designer/{id}` is a **different editing paradigm** — not freeform canvas placement, but a **stack of configurable content blocks** (company header, customer info, items table, tax summary, payment info, signature, amount-in-words, etc.), each either a hardcoded default block template or generated from structured settings (`_compile_block_from_settings()` and its per-block-type callees in `admin_print_template.php`). It includes an embedded **GrapesJS 0.21.13** instance (loaded from `unpkg`), but only inside a **"Block Designer" modal** used to hand-author the raw HTML/CSS of an individual custom block — GrapesJS is not the primary page-canvas editing surface for V1 (an earlier internal write-up describing V1 as a full-page GrapesJS canvas is describing an earlier iteration and should be treated as superseded by this finding). V1's save path (`save_design()`) accepts either this newer "blocks" format or a legacy raw GrapesJS HTML/CSS format for backward compatibility, and follows the same insert-new-version pattern as V2 described in [06_Update_Rules.md](06_Update_Rules.md).
