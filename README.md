# microscrap/metal-gfx — Metal Deferred framebuffer for ScrapyardIO

[![Docs](https://img.shields.io/badge/docs-ScrapyardIO-0ea5e9)](https://scrapyard-io.projectsaturnstudios.com/ecosystem/microscrap/metal-gfx/0.7.x/overview)
[![PHP](https://img.shields.io/badge/php-%5E8.4%7C%5E8.5%7C%5E8.6-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![Platform](https://img.shields.io/badge/platform-macOS-lightgrey)](https://developer.apple.com/metal/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

**Docs:** [ScrapyardIO — microscrap/metal-gfx `0.7.x`](https://scrapyard-io.projectsaturnstudios.com/ecosystem/microscrap/metal-gfx/0.7.x/overview)

Metal companion for ScrapyardIO **tubes 0.7** — Deferred framebuffer key `metal` plus a live `MetalWindowHandler` for `Window::driver('metal')`.

## Highlights

* `MetalHandledFramebuffer` extends `DeferredFramebuffer` (not Managed / not `PixelStore`)
* **Headless** — system `MTLDevice` + queue + offscreen RGBA8 `MTLTexture`
* **Windowed** — `MetalWindowHandler` opens an `NSWindow`; `present()` blits texture → `CAMetalLayer` (**no PHP flush**)
* **`MetalRenderer2D`** — full tubes `DrawingAPI` + `DrawsText` into the borrowed Metal framebuffer (`fill` uses GPU texture clear)
* Framebuffer key `metal` via `extendDeferred` + publish tag `tubes-framebuffers-metal`
* Window slug `metal` via `WindowFactory::extend` + publish tag `tubes-windows-metal`

## Requirements

* PHP 8.4+
* **macOS** + **ext-metal** ^0.7.2
* `microscrap/metal` ^0.7.2
* `scrapyard-io/tubes` ^0.7.0

## Installation

```bash
php -m | grep metal
composer require microscrap/metal-gfx:^0.7.0
php workshop vendor:publish --tag=tubes-framebuffers-metal
php workshop vendor:publish --tag=tubes-windows-metal
# or: php workshop install:gfx metal
```

## Usage

### Headless framebuffer

```php
use ScrapyardIO\Tubes\Core\MagicAliases\Framebuffer;
use Microscrap\GFX\Metal\MetalHandledFramebuffer;

$buffer = Framebuffer::driver('metal')
    ->size(320, 240)
    ->format(MetalHandledFramebuffer::rgbaSpec())
    ->create();

$buffer->setPixel(10, 10, 0xFF0000FF);
```

### Visible OS window + DrawingAPI

```php
use ScrapyardIO\Tubes\Core\MagicAliases\Window;
use Microscrap\GFX\Metal\MetalRenderer2D;

$window = Window::driver('metal')->title('Metal')->size(800, 600)->open();
$fb = $window->framebuffer(); // MetalHandledFramebuffer, not headless
$gfx = (new MetalRenderer2D)->setFramebuffer($fb);
$gfx->fill(0xFF203040)
    ->fillCircle(400, 300, 80, 0xE0E0E0FF)
    ->setTextColor(0xFFFFFFFF)
    ->setCursor(40, 40)
    ->print('Metal');
$window->present()->pollEvents();
$window->close();
```

## Stack

```text
ext-metal ≥ 0.7.2 + microscrap/metal
  └── microscrap/metal-gfx   (Deferred + Window key metal)  ← this package
        └── scrapyard-io/tubes factory
```

## License

MIT © Angel Gonzalez
