---
type: Core
title: MetalRenderer2D
description: DrawingAPI implementation — borrows MetalHandledFramebuffer; fill uses GPU texture clear
tags: [core, rendering, drawing, metal]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T05:20:00Z" }
sources:
  - id: renderer
    resource: ../src/MetalRenderer2D.php
    title: MetalRenderer2D
---

# Role

`MetalRenderer2D` extends tubes `Renderer2D` and implements the full `DrawingAPI`. Presentation owns the framebuffer; this class **borrows** it via `setFramebuffer(&$fb)`.

# Metal-aware behavior

| Call | Path |
|------|------|
| `fill($color)` | `MetalHandledFramebuffer::fill` → `mtl_texture_clear` (GPU) |
| pixels / segments / lines | `setPixel` / `setSegment` into the offscreen MTLTexture |
| circles / ellipses / triangles / roundrects | Midpoint / scanline into the texture |
| Text (`setFont` / `print` / …) | tubes `DrawsText` concern → `drawPixel` / `fillRect` into Metal FB |
| Present | Not this class — WindowHandler / `framebuffer()->present()` → `mtl_window_present_texture` |

# Text

Uses `ScrapyardIO\Tubes\Rendering\Concerns\DrawsText` (same soft path as sketch Renderer2D):

- Default / `setFont('classic')` / `setFont(null)` → built-in `ClassicFont` 5×7
- `setFont($gfxFont)` or registry slug → custom `GFXFont` (needs Font manager for string slugs)

# Usage

```php
$window = Window::driver('metal')->title('Metal')->size(800, 600)->open();
$fb = $window->framebuffer();
$gfx = (new MetalRenderer2D)->setFramebuffer($fb);
$gfx->fill(0x203040FF)
    ->fillCircle(400, 300, 80, 0xE0E0E0FF)
    ->setTextColor(0xFFFFFFFF)
    ->setCursor(40, 40)
    ->print('Metal');
$window->present()->pollEvents();
```

# Related

- Tubes Rendering / Fonts OKF (`scrapyard-io/tubes` `.okf/core/rendering.md`, `fonts.md`)
- [MetalHandledFramebuffer](metal-handled-framebuffer.md)
- [MetalWindowHandler](metal-window-handler.md)
- [Metal VSync](vsync.md)
