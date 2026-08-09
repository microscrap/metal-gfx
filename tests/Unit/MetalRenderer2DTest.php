<?php

use Microscrap\GFX\Metal\MetalHandledFramebuffer;
use Microscrap\GFX\Metal\MetalRenderer2D;
use ScrapyardIO\Tubes\Contracts\Rendering\DrawingAPI;
use ScrapyardIO\Tubes\Contracts\Rendering\RenderingException;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

beforeEach(function (): void {
    if (! extension_loaded('metal')) {
        $this->markTestSkipped('ext-metal is not loaded');
    }
});

function metalRendererBound(int $w = 64, int $h = 48): array
{
    $fb = MetalHandledFramebuffer::sized($w, $h, MetalHandledFramebuffer::rgbaSpec());
    $renderer = new MetalRenderer2D;
    $renderer->setFramebuffer($fb);

    return [$renderer, $fb];
}

test('MetalRenderer2D implements DrawingAPI and Renderer2D', function () {
    $renderer = new MetalRenderer2D;

    expect($renderer)->toBeInstanceOf(Renderer2D::class)
        ->and($renderer)->toBeInstanceOf(DrawingAPI::class);
});

test('MetalRenderer2D throws when drawing unbound', function () {
    $renderer = new MetalRenderer2D;

    expect(fn () => $renderer->drawPixel(0, 0, 0xFFFFFFFF))
        ->toThrow(RenderingException::class);
});

test('MetalRenderer2D fill uses Metal texture clear', function () {
    [$renderer, $fb] = metalRendererBound(16, 12);

    $renderer->fill(0x112233FF);

    expect($fb->getPixel(0, 0))->toBe(0x112233FF)
        ->and($fb->getPixel(15, 11))->toBe(0x112233FF);
});

test('MetalRenderer2D drawPixel and drawLine write into the borrowed Metal buffer', function () {
    [$renderer, $fb] = metalRendererBound();

    $renderer->fill(0x000000FF)
        ->drawPixel(5, 5, 0xFF0000FF)
        ->drawLine(0, 0, 10, 0, 0x00FF00FF);

    expect($fb->getPixel(5, 5))->toBe(0xFF0000FF)
        ->and($fb->getPixel(0, 0))->toBe(0x00FF00FF)
        ->and($fb->getPixel(10, 0))->toBe(0x00FF00FF);
});

test('MetalRenderer2D rect circle triangle primitives paint expected pixels', function () {
    [$renderer, $fb] = metalRendererBound(80, 60);

    $renderer->fill(0x000000FF)
        ->drawRect(2, 2, 10, 8, 0xAAAAAAFF)
        ->fillRect(20, 4, 6, 4, 0x00FFFFFF)
        ->drawCircle(40, 30, 8, 0xFF00FFFF)
        ->fillCircle(60, 30, 5, 0xFFFF00FF)
        ->drawTriangle(5, 40, 15, 55, 1, 55, 0xFFFFFFFF)
        ->fillTriangle(50, 45, 70, 45, 60, 55, 0x888888FF);

    expect($fb->getPixel(2, 2))->toBe(0xAAAAAAFF)
        ->and($fb->getPixel(22, 5))->toBe(0x00FFFFFF)
        ->and($fb->getPixel(40, 22))->toBe(0xFF00FFFF) // top of stroke circle
        ->and($fb->getPixel(60, 30))->toBe(0xFFFF00FF) // center of filled circle
        ->and($fb->getPixel(60, 50))->toBe(0x888888FF); // inside filled triangle
});

test('MetalRenderer2D works with a window-bound Metal framebuffer', function () {
    $handler = new Microscrap\GFX\Metal\MetalWindowHandler('renderer', 96, 72);
    $handler->open();
    $fb = $handler->framebuffer();

    $renderer = new MetalRenderer2D;
    $renderer->setFramebuffer($fb);
    $renderer->fill(0x203040FF)
        ->fillCircle(48, 36, 12, 0xE0E0E0FF)
        ->drawRect(8, 8, 80, 56, 0xFFFFFFFF);

    $handler->present()->pollEvents();

    expect($fb->isHeadless())->toBeFalse()
        ->and($fb->getPixel(48, 36))->toBe(0xE0E0E0FF);

    $handler->close();
});

test('MetalRenderer2D DrawsText prints classic glyphs into the Metal buffer', function () {
    [$renderer, $fb] = metalRendererBound(80, 24);

    $renderer->fill(0x000000FF)
        ->setTextColor(0xFFFFFFFF)
        ->setCursor(2, 2)
        ->setFont('classic')
        ->print('Hi');

    // Classic 5×7: at least one foreground pixel lit in the first glyph cell.
    $lit = false;
    for ($y = 2; $y < 10; $y++) {
        for ($x = 2; $x < 8; $x++) {
            if ($fb->getPixel($x, $y) === 0xFFFFFFFF) {
                $lit = true;
                break 2;
            }
        }
    }

    expect($lit)->toBeTrue()
        ->and($renderer->getCursorX())->toBeGreaterThan(2);
});

test('MetalRenderer2D getTextBounds returns a box for classic text', function () {
    [$renderer] = metalRendererBound(120, 40);

    $bounds = $renderer->setFont(null)->getTextBounds('AB', 0, 0);

    expect($bounds)->toHaveKeys(['x1', 'y1', 'w', 'h'])
        ->and($bounds['w'])->toBeGreaterThan(0)
        ->and($bounds['h'])->toBeGreaterThan(0);
});
