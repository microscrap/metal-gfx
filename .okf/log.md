# OKF log

## 2026-08-12

- **Tetriminos heap (debug-641660)**: PLAY Zend used-heap climb was input poll, not Metal VRAM. Fixes: scalar `mouseX`/`mouseY`/`mouseScrollY` (no `[x,y]` hashtables); `Menu::pollAction` `RETURN_EMPTY_STRING`; GameController rebuild-once + in-place button/stick mutate (do not cache count=0 — GCController list fills after discovery). Verified pad count=1 and PLAY `phpUsedMb` 11.18→11.23 / poll deltas 0.

- **Input snapshot reuse**: `MetalInputHandler` caches `KeyCode::cases()` and mutates the existing Mouse/DigitalButton devices on poll (no per-frame `new Mouse`).

- **Dual-host**: `MetalGfxServiceProvider` skips extend when `ext-metal` is not loaded so Jetson can require metal-gfx from the same `composer.json`.
- **VSync core**: [Metal VSync](core/vsync.md) — `mtl_window_set_display_sync` when the ABI exists. Verified 2026-08-12 without the ABI: VSync OFF + Uncapped still exceeded a 120 Hz panel (`presentMs` ~0.1 ms, delivered 121–131 Hz).
- **Composer require**: dropped kitchen-sink `scrapyard-io/tubes`. Require only used tubes components: `contracts`, `framebuffers`, `rendering`, `fonts` (DrawsText), `windows`, `human-input`, `inputs`.
- **setSegment fill_rect**: `MetalHandledFramebuffer::setSegment` uses `mtl_texture_fill_rect` (ext-metal / microscrap/metal **0.7.4**) — one region upload per rect. Requires `ext-metal` `^0.7.4`, `microscrap/metal` `^0.7.4`. Package version **0.7.3**.

## 2026-08-11

- **PanelIC dirty+PARTIAL+RGB565**: `MetalHandledFramebuffer` tracks dirty rects; headless `flush` coalesces → `FULL`/`PARTIAL` `DumpedBuffer`s; one shared `mtl_texture_read_rgba8` then slice+pack; fast ROW_MAJOR B32/B16 pack (`0xRRGGBBAA` → RGB565, not low-16 bug); `damageGranularity` pixel when headless; `preservesContentsOnPresent` when headless; `MetalRenderer2D` `fillCircle`/`drawCircle` use `deferDirty`. Pest mirrors ogx/sdl3. Windowed present unchanged.

## 2026-08-09

- **Human Input**: `MetalInputHandler` snapshots `mtl_input_*` (ext-metal / microscrap/metal **0.7.3**) into Keyboard/Mouse/GameController. `MetalWindowHandler::pollNative()` fans out after `mtl_app_poll`. Window mouse Y flipped to framebuffer top-left. Pest `MetalInputHandlerTest`.
- **Fonts / DrawsText**: `MetalRenderer2D` uses tubes `DrawsText` for `setFont` / `print` / `drawChar` / bounds (ClassicFont + GFXFont). Docs + Pest updated.
- **Renderer2D**: Implemented full `DrawingAPI` on `MetalRenderer2D` (GPU `fill` via texture clear; midpoint circles/ellipses; scanline triangles; roundrects). Pest coverage for headless + window-bound buffers.
- **Window live**: `MetalWindowHandler` boots NSWindow; `attachedTo` + `present()` via `mtl_window_present_texture` (ext-metal **0.7.2**). Pest open/present/poll/close green. Trap `window-attach-later` marked historical/deprecated.
- **WindowHandler scaffold**: Documented `MetalWindowHandler` + `WindowFactory::extend('metal')` + publish `tubes-windows-metal`. Verified Pest (FormatSpec, OSWindow wrap, manager create, `open()` throws until `bootNative`). Native AppKit/CAMetalLayer boot and `attachedTo` still deferred.
- **Amend (draft)**: `MetalHandledFramebuffer extends DeferredFramebuffer`; `fill(int)` + no-op `present()`; publish stub `tubes-framebuffers-metal` → `config/framebuffers/metal.php`.

## 2026-08-08

- **Update**: Headless backing is offscreen `MTLTexture` via **ext-metal 0.7.1** (not a CPU shadow store). Requires `microscrap/metal` ^0.7.1.
- Initial bundle for `microscrap/metal-gfx` 0.7.0 — Deferred `metal` / `MetalHandledFramebuffer` headless. Window attach deferred to tubes OSWindows.
