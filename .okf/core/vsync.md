---
type: Core
title: Metal VSync
description: "setVsync — mtl_window_set_display_sync when ext-metal provides it; otherwise the flag is stored. Present is a GPU blit."
resource: src/MetalWindowHandler.php
tags: [core, vsync, metal, macos]
generated: { by: "cursor-agent/grok-4.6", at: "2026-08-12T19:35:00Z" }
status: draft
---

# Role

`MetalWindowHandler::setVsync(bool)` is the present-lock toggle. `presentNative()` blits via `mtl_window_present_texture` (no PHP flush). Hardware vsync is a **floor at the panel refresh**.

| Path | Behavior |
|------|----------|
| `mtl_window_set_display_sync` exists | Call it with the requested flag |
| ABI missing | Store the flag only |

Verified 2026-08-12 **without** the ABI: VSync OFF + Uncapped still exceeded a 120 Hz panel (`presentMs` ~0.1 ms, delivered 121–131 Hz). Paint (~8 ms `tickMs`) straddles 120 — that is CPU/GPU fill cost, not a compositor wait.

VSync OFF + Uncapped must be allowed to exceed the panel refresh. Numbered caps belong to the app `FramePacer`.

# Related

- [MetalWindowHandler](metal-window-handler.md)
- [MetalHandledFramebuffer](metal-handled-framebuffer.md)
