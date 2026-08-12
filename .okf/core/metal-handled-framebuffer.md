---
type: Core
title: MetalHandledFramebuffer
description: Tubes DeferredFramebuffer — headless sized() or windowed attachedTo(); PanelIC dirty+PARTIAL+fast RGB565 pack; present blits texture to CAMetalLayer
tags: [core, framebuffer, deferred, metal, panelic, dirty, rgb565]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-11T04:30:00Z" }
---

# Role

`Microscrap\GFX\Metal\MetalHandledFramebuffer` **extends** `ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer`.

## Headless — `::sized($w, $h, $hostFormat)`

Owns MTLDevice + queue + offscreen RGBA8 MTLTexture. `isHeadless()` → `true`. `present()` is a no-op.

### PanelIC flush (dirty + PARTIAL + fast pack)

- Draw paths (`setPixel` / `setSegment` / `fill`) track inclusive dirty rects; `fill` → `markAllDirty()`.
- Windowed / headless `setSegment` uses **`mtl_texture_fill_rect`** (ext-metal **0.7.4+**) — one `replaceRegion` per solid rect. Do not loop `writePixel`.
- `flush($spec, as_array: true)` coalesces dirty → `DumpedBuffer` with `RenderType::FULL` (whole surface) or `PARTIAL` (origin + size). Empty dirty → `[]` / `''`.
- Pack host words `0xRRGGBBAA` via chunked `pack()`: ROW_MAJOR B32 MSB → `N*`; ROW_MAJOR B16 → RGB565 from R/G/B channels (**not** low-16-bits-as-RGB565). Other specs fall back to `PixelStore`.
- GPU readback: one `mtl_texture_read_rgba8()` per flush (`readTextureWords`), then slice dirty regions in PHP (no region-read API). SPI only receives dirty bytes via PARTIAL origins.
- `damageGranularity()` → `pixel()` when headless; `wholeSurface()` when windowed.
- `preservesContentsOnPresent()` → `true` when headless (offscreen texture survives; PanelIC can erase/prime for PARTIAL).
- `MetalRenderer2D::fillCircle` / `drawCircle` wrap drawing in `deferDirty()` so Bresenham hlines/pixels become one dirty bbox.

## Windowed — `::attachedTo($window, $spec, $w, $h)`

Requires `mtl_window_attach_device` first. Borrows the window device (`owns_device = false`); owns queue + texture. `isHeadless()` → `false`. `present()` → `mtl_window_present_texture($window, $texture)` (GPU blit; no PHP flush remux). Headless-only PanelIC flush: windowed `flush` presents and returns empty.

## App usage

```php
Framebuffer::driver('metal')->size(320, 240)->format($spec)->create(); // headless

// windowed via WindowHandler:
Window::driver('metal')->title('x')->size(640, 480)->open();
```
