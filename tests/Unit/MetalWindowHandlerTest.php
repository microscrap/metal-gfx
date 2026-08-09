<?php

use Microscrap\GFX\Metal\MetalHandledFramebuffer;
use Microscrap\GFX\Metal\MetalWindowHandler;
use ScrapyardIO\Tubes\Canvas\OSWindow;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\Endianness;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\FramebufferKind;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Framebuffers\FramebufferManager;
use ScrapyardIO\Tubes\Framebuffers\PendingFramebuffer;
use ScrapyardIO\Tubes\Windows\WindowHandler;
use ScrapyardIO\Tubes\Windows\WindowManager;

beforeEach(function (): void {
    if (! extension_loaded('metal')) {
        $this->markTestSkipped('ext-metal is not loaded');
    }
});

test('MetalWindowHandler defines rgba FormatSpec at construct', function () {
    $handler = new MetalWindowHandler('demo', 320, 240);

    expect($handler)->toBeInstanceOf(WindowHandler::class)
        ->and($handler->title())->toBe('demo')
        ->and($handler->width())->toBe(320)
        ->and($handler->height())->toBe(240)
        ->and($handler->isOpen())->toBeFalse()
        ->and($handler->formatSpec())->toEqual(MetalHandledFramebuffer::rgbaSpec())
        ->and($handler->formatSpec()->bit_depth)->toBe(BitDepth::B32)
        ->and($handler->formatSpec()->pixel_format)->toBe(PixelFormat::ROW_MAJOR)
        ->and($handler->formatSpec()->endianness)->toBe(Endianness::MSB);
});

test('MetalWindowHandler open present poll close updates a visible window path', function () {
    $handler = new MetalWindowHandler('metal-gfx-test', 64, 48);
    $handler->open();

    expect($handler->isOpen())->toBeTrue()
        ->and($handler->metalWindow())->toBeGreaterThan(0)
        ->and($handler->metalDevice())->toBeGreaterThan(0);

    $fb = $handler->framebuffer();
    expect($fb)->toBeInstanceOf(MetalHandledFramebuffer::class)
        ->and($fb->isHeadless())->toBeFalse()
        ->and($fb->metalWindow())->toBe($handler->metalWindow());

    $fb->fill(0xFF203040)->setPixel(10, 10, 0xFFFFFFFF);
    expect($fb->getPixel(10, 10))->toBe(0xFFFFFFFF);

    $handler->present()->pollEvents();

    expect($handler->shouldClose())->toBeFalse();

    $handler->close();
    expect($handler->isOpen())->toBeFalse()
        ->and($handler->metalWindow())->toBe(0);
});

test('OSWindow wraps MetalWindowHandler and open works', function () {
    $window = new OSWindow(new MetalWindowHandler('canvas', 80, 60));
    $window->open();

    expect($window->title())->toBe('canvas')
        ->and($window->width())->toBe(80)
        ->and($window->height())->toBe(60)
        ->and($window->formatSpec())->toEqual(MetalHandledFramebuffer::rgbaSpec())
        ->and($window->framebuffer())->toBeInstanceOf(MetalHandledFramebuffer::class)
        ->and($window->framebuffer()->isHeadless())->toBeFalse();

    $window->framebuffer()->fill(0x112233FF);
    $window->present()->pollEvents();
    $window->close();
});

test('WindowManager extend metal creates and opens OSWindow', function () {
    $manager = new WindowManager;
    $manager->extend('metal', MetalWindowHandler::class);

    $window = $manager->driver('metal')
        ->title('mgr')
        ->size(96, 72)
        ->open();

    expect($window)->toBeInstanceOf(OSWindow::class)
        ->and($window->isOpen())->toBeTrue()
        ->and($window->framebuffer()->isHeadless())->toBeFalse();

    $window->close();
});

test('extendDeferred metal creates via FramebufferManager', function () {
    $manager = new FramebufferManager;

    $manager->extendDeferred(
        'metal',
        fn (PendingFramebuffer $pending) => MetalHandledFramebuffer::sized(
            $pending->widthValue(),
            $pending->heightValue(),
            $pending->hostFormatValue(),
        ),
    );

    $buffer = $manager->driver('metal')
        ->size(8, 8)
        ->format(MetalHandledFramebuffer::rgbaSpec())
        ->create();

    expect($buffer)->toBeInstanceOf(MetalHandledFramebuffer::class)
        ->and($buffer->isHeadless())->toBeTrue()
        ->and($manager->kindOf('metal'))->toBe(FramebufferKind::DEFERRED);
});
