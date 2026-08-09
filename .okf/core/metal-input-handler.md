---
type: Core
title: MetalInputHandler
description: tubes InputHandler — snapshots ext-metal mtl_input_* into Keyboard/Mouse/GameController
tags: [core, input, metal, human-input]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T07:00:00Z" }
sources:
  - id: handler
    resource: ../src/MetalInputHandler.php
    title: MetalInputHandler
  - id: window
    resource: ../src/MetalWindowHandler.php
    title: MetalWindowHandler poll fan-out
---

# Role

`MetalInputHandler` is the Metal companion for tubes `Inputs\InputHandler`. It reads **microscrap/metal** `mtl_input_*` helpers (ext-metal ≥ **0.7.3**) into:

- `Keyboard` — key names = `KeyCode` enum case names (`W`, `SPACE`, …)
- `Mouse` — position + left/right/middle `DigitalButton`; wheel from scroll Y
- `GameController[]` — each `GCController` pad (sticks + buttons + triggers); `gamePads()` stays empty

# Poll ownership

`MetalInputHandler::poll()` **does not** call `mtl_app_poll()`.

`MetalWindowHandler::pollNative()` owns the AppKit pump:

1. `mtl_app_poll()`
2. menu quit → terminate
3. `$this->inputHandler()->poll()` — fan-out into Human Input devices

Access via `MetalWindowHandler::inputHandler()` and wrap with `EngineInput` when needed.

# Coordinates

Window-relative mouse uses content-view coords with **Y flipped to top-left** (framebuffer space). Screen coords (`window_handler` unset / no native window) keep AppKit Y-up.

# Related

- [MetalWindowHandler](metal-window-handler.md)
- tubes [InputHandler](../../../../scrapyard-io/tubes/.okf/core/input-handler.md) (path may differ in dist)
