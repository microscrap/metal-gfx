---
okf_version: "0.2"
---

# microscrap/metal-gfx Knowledge Bundle

Package knowledge for `microscrap/metal-gfx` (Metal Deferred framebuffer + live WindowHandler + Human Input over **ext-metal** ≥ 0.7.3 / microscrap/metal, v0.7.0).
Read this index first; open only the concepts needed for the task.

**Trust rule:** Prefer `status: stable`. Treat `deprecated` as historical only. New agent-written concepts stay `status: draft` until a human verifies them.
**Placement:** This bundle lives at the **package root** only — never under `src/`.
**Scope:** Document Deferred `metal` registration, headless `MetalHandledFramebuffer`, windowed `MetalWindowHandler`, and `MetalRenderer2D` DrawingAPI. Soft Managed drivers live in tubes.
**Dist note:** `.okf/` and root `AGENTS.md` are `export-ignore` in `.gitattributes`.

# Orientation

* [Package (0.7)](orientation/package.md) - Composer identity; Deferred + Window keys `metal`.

# Core

* [MetalGfxServiceProvider](core/service-provider.md) - `extendDeferred('metal')` + `WindowFactory::extend('metal')`.
* [MetalHandledFramebuffer](core/metal-handled-framebuffer.md) - Headless `sized` / windowed `attachedTo`.
* [MetalWindowHandler](core/metal-window-handler.md) - Visible OS window; present via GPU blit; poll fans out to input.
* [MetalInputHandler](core/metal-input-handler.md) - Human Input companion over `mtl_input_*` (0.7.3+).
* [MetalRenderer2D](core/metal-renderer-2d.md) - Full DrawingAPI into borrowed Metal framebuffer.

# Conventions

* [Registration key](conventions/registration-key.md) - Key stays `metal` on Deferred + Window lanes.

# Traps

* [Window attach (historical)](traps/window-attach-later.md) - Deprecated; native boot + attachedTo land in 0.7.

# Log

* [Directory update log](log.md)
