---
type: Trap
title: Window attach (historical)
description: Resolved in 0.7 — MetalWindowHandler boots NSWindow; attachedTo + presentTexture work
tags: [trap, metal, window, historical]
status: deprecated
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:30:00Z" }
---

# Status

**Resolved.** Keep this note so agents do not resurrect the stub-throwing path.

# Was

- `MetalWindowHandler::bootNative()` threw `WindowException`
- `MetalHandledFramebuffer::attachedTo(...)` threw `MetalGfxException::windowAttachNotReady()`

# Now

1. `MetalWindowHandler` creates/shows an `NSWindow`, attaches `MTLDevice`, binds `MetalHandledFramebuffer::attachedTo`.
2. Draw into the offscreen RGBA8 texture (`fill` / `setPixel`).
3. `present()` / `presentNative()` call `mtl_window_present_texture` (ext-metal ≥ **0.7.2**) — GPU blit to `CAMetalLayer`, **no PHP flush**.
4. `pollEvents()` → `mtl_app_poll()`; `shouldClose()` → `mtl_window_should_close` / app quit.

See [MetalWindowHandler](../core/metal-window-handler.md).
