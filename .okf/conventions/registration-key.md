---
type: Convention
title: Registration key
description: Factory keys stay metal on Deferred framebuffer and Window lanes.
tags: [convention, metal, deferred, window]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:20:00Z" }
---

# Rule

| Lane | Registration | Publish tag |
|------|--------------|-------------|
| Deferred framebuffer | `extendDeferred('metal', …)` | `tubes-framebuffers-metal` |
| Window | `WindowFactory::extend('metal', MetalWindowHandler::class)` | `tubes-windows-metal` |

Do **not** use `extendManaged` for metal. Soft Managed stays tubes `full` / `dirty` / `page`.

Discovery / Workshop / docs keep the slug **`metal`** stable on both lanes.
