---
type: Core
title: MetalHandledFramebuffer
description: Tubes DeferredFramebuffer — headless sized() or windowed attachedTo(); present blits texture to CAMetalLayer
tags: [core, framebuffer, deferred, metal]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:30:00Z" }
---

# Role

`Microscrap\GFX\Metal\MetalHandledFramebuffer` **extends** `ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer`.

## Headless — `::sized($w, $h, $hostFormat)`

Owns MTLDevice + queue + offscreen RGBA8 MTLTexture. `isHeadless()` → `true`. `present()` is a no-op.

## Windowed — `::attachedTo($window, $spec, $w, $h)`

Requires `mtl_window_attach_device` first. Borrows the window device (`owns_device = false`); owns queue + texture. `isHeadless()` → `false`. `present()` → `mtl_window_present_texture($window, $texture)` (GPU blit; no PHP flush remux).

## App usage

```php
Framebuffer::driver('metal')->size(320, 240)->format($spec)->create(); // headless

// windowed via WindowHandler:
Window::driver('metal')->title('x')->size(640, 480)->open();
```
