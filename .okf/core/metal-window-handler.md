---
type: Core
title: MetalWindowHandler
description: tubes WindowHandler — NSWindow + MetalHandledFramebuffer; present via mtl_window_present_texture
tags: [core, window, metal, deferred]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:30:00Z" }
sources:
  - id: handler
    resource: ../src/MetalWindowHandler.php
    title: MetalWindowHandler
  - id: config
    resource: ../config/windows/metal.php
    title: windows/metal.php publish stub
  - id: provider
    resource: ../src/Providers/MetalGfxServiceProvider.php
    title: MetalGfxServiceProvider
---

# Role

`MetalWindowHandler` drives a visible macOS window for tubes `OSWindow`. Host packing is fixed to [`MetalHandledFramebuffer::rgbaSpec()`](metal-handled-framebuffer.md).

# Lifecycle

| Hook | Behavior |
|------|----------|
| `bootNative()` | `mtl_app_init` (once), default menu (once), create device + window, `attach_device`, `show` |
| `bindFramebuffer()` | `MetalHandledFramebuffer::attachedTo(window, …)` — same device as the window |
| `presentNative()` | `$framebuffer->present()` → `mtl_window_present_texture` |
| `pollNative()` | `mtl_app_poll` (+ menu `quit` → terminate) then `inputHandler()->poll()` |
| `shouldClose()` | `mtl_window_should_close` \|\| `mtl_app_should_quit` |
| `close()` / `destroyNative()` | Drop FB first, then destroy window + release device; does **not** call `mtl_app_terminate` |

Human Input: construct builds a [`MetalInputHandler`](metal-input-handler.md); expose via `inputHandler()`. Requires **ext-metal ≥ 0.7.3**.

# Usage

```php
$window = Window::driver('metal')->title('Metal')->size(800, 600)->open();
$fb = $window->framebuffer(); // MetalHandledFramebuffer, isHeadless() === false
$fb->fill(0xFF203040)->setPixel(10, 10, 0xFFFFFFFF);
$window->present()->pollEvents();
$window->close();
```

Requires **ext-metal ≥ 0.7.2** (`Window::getDevice`, `Window::presentTexture`).
