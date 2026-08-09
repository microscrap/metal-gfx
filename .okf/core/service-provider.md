---
type: Core
title: MetalGfxServiceProvider
description: Package discovery provider; boot registers extendDeferred('metal') and WindowFactory extend('metal')
tags: [core, provider, deferred, window]
status: draft
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:15:00Z" }
---

# Boot

```php
// Framebuffers — Deferred lane
$this->publishes([
    __DIR__.'/../../config/framebuffers/metal.php' => $this->container->configPath('framebuffers/metal.php'),
], 'tubes-framebuffers-metal');

$framebuffers->extendDeferred(
    'metal',
    fn (PendingFramebuffer $pending) => MetalHandledFramebuffer::sized(
        $pending->widthValue(),
        $pending->heightValue(),
        $pending->hostFormatValue(),
    ),
);

// Windows — handler class registration
$this->publishes([
    __DIR__.'/../../config/windows/metal.php' => $this->container->configPath('windows/metal.php'),
], 'tubes-windows-metal');

$windows->extend('metal', MetalWindowHandler::class);
```

**Deferred lane** for buffers — not `extendManaged`. Managed is for soft `PixelStore` strategies.

**Window slug** `metal` → [`MetalWindowHandler`](metal-window-handler.md) (live NSWindow + presentTexture).
