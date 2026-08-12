---
type: Orientation
title: Package (0.7)
description: microscrap/metal-gfx 0.7.0 — Deferred framebuffer metal + WindowHandler stub
tags: [metal-gfx, microscrap, deferred, macos, window]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:15:00Z" }
---

# Identity

| Field | Value |
|-------|--------|
| Composer | `microscrap/metal-gfx` **0.7.0** |
| PHP | `^8.4\|^8.5\|^8.6` |
| Requires | `ext-metal` ^0.7.4, `microscrap/metal` ^0.7.4, `fabricate/nuts-and-bolts`, tubes components: `contracts`, `framebuffers`, `rendering`, `fonts`, `windows`, `human-input`, `inputs` (**not** `scrapyard-io/tubes`) |
| Namespace | `Microscrap\GFX\Metal\` |
| Role | Deferred `MetalHandledFramebuffer` + live `MetalWindowHandler` |
| Platform | **macOS** |

# Lanes

| Lane | Driver / slug |
|------|----------------|
| Managed (tubes) | `full` / `dirty` / `page` |
| Deferred framebuffer (this) | `metal` — headless MTLTexture default |
| Window (this) | `metal` — `MetalWindowHandler` (NSWindow + presentTexture) |
