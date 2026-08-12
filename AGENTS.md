# Agent guidelines — microscrap/metal-gfx

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from Composer dist via `.gitattributes` `export-ignore`).

Before changing GFX/framebuffer code **for this package**:

1. Read [`.okf/index.md`](.okf/index.md) first (progressive disclosure).
2. Open only the linked concepts needed for the task.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only. New/changed concepts stay `status: draft` until a human verifies them.
4. When you learn something durable about **this package**, update the affected `.okf` concept(s) and append `.okf/log.md`.
5. Keep the `.okf` bundle at the **package root** only — do not nest extra `.okf` folders under `src/`.
6. Bindings knowledge → `microscrap/metal`. Tubes PixelStore/factories → discrete `tubes/*` components (never blanket `scrapyard-io/tubes` in this package’s `require`). Extension build → `php-io-extensions/metal`.
7. **Always** keep the `.okf/` bundle current when changing API or registration; append `.okf/log.md`.
8. **NEVER** commit or push `vendor/`.

## Package rules (quick) — 0.7.x

- Composer: `microscrap/metal-gfx` **0.7.3**. PHP `^8.4|^8.5|^8.6`.
- Depends on `microscrap/metal` ^0.7.4, `ext-metal` ^0.7.4, `fabricate/nuts-and-bolts` (`^0.7.0`), and tubes components only: `tubes/{contracts,framebuffers,rendering,fonts,windows,human-input,inputs}`.
- Provider registers **`extendDeferred('metal', …)`** — **not** `extendManaged`. Soft Managed = tubes `full`/`dirty`/`page` only.
- Provider also **`WindowFactory::extend('metal', MetalWindowHandler::class)`** + publish `tubes-windows-metal`.
- `MetalHandledFramebuffer` implements **`DeferredFramebuffer`**.
- **Headless** `::sized()` = device + queue + offscreen RGBA8 `MTLTexture`.
- **Windowed** `MetalWindowHandler` + `::attachedTo()` — `present()` uses `mtl_window_present_texture` (no PHP flush).
- **VSync**: `MetalWindowHandler::setVsync` → `mtl_window_set_display_sync` when ext-metal provides it. VSync OFF + Uncapped must be allowed to exceed the panel refresh.
- **Human Input**: `MetalInputHandler` + `MetalWindowHandler::inputHandler()`; `pollNative()` fans out after `mtl_app_poll` (do not double-pump in `InputHandler::poll`).
- `MetalRenderer2D` implements tubes `DrawingAPI` against a borrowed framebuffer (`fill` → Metal texture clear).
- Text: `use DrawsText` (tubes concern) — do not reimplement glyph rasterization in metal-gfx.
- Never model `metal` as a PHP Managed `PixelStore` concrete.
- Registration key stays **`metal`**. Namespace `Microscrap\GFX\Metal\`.
- User-facing copy says **macOS**, never Darwin.
