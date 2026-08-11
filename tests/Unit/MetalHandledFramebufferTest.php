<?php

use Microscrap\GFX\Metal\MetalGfxException;
use Microscrap\GFX\Metal\MetalHandledFramebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer as DeferredFramebufferAbstract;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\Endianness;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\FramebufferKind;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\RenderType;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Contracts\Framebuffers\ManagedFramebuffer as ManagedFramebufferContract;
use ScrapyardIO\Tubes\Framebuffers\FramebufferManager;
use ScrapyardIO\Tubes\Framebuffers\ManagedFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\PendingFramebuffer;

function metalRowMajor(): FormatSpec
{
    return new FormatSpec(
        PixelFormat::ROW_MAJOR,
        BitDepth::B32,
        endianness: Endianness::MSB,
    );
}

beforeEach(function (): void {
    if (! extension_loaded('metal')) {
        $this->markTestSkipped('ext-metal is not loaded');
    }
});

test('sized builds a deferred headless Metal-handled buffer', function () {
    $buffer = MetalHandledFramebuffer::sized(8, 8, metalRowMajor());

    expect($buffer)->toBeInstanceOf(MetalHandledFramebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebufferAbstract::class)
        ->and($buffer)->not->toBeInstanceOf(ManagedFramebuffer::class)
        ->and($buffer)->not->toBeInstanceOf(ManagedFramebufferContract::class)
        ->and($buffer->isHeadless())->toBeTrue()
        ->and($buffer->viewportWidth())->toBe(8)
        ->and($buffer->viewportHeight())->toBe(8)
        ->and($buffer->metalDevice())->toBeGreaterThan(0)
        ->and($buffer->metalQueue())->toBeGreaterThan(0)
        ->and($buffer->metalTexture())->toBeGreaterThan(0)
        ->and($buffer->metalDeviceName())->toBeString()->not->toBeEmpty();
});

test('extendDeferred metal creates via FramebufferManager driver', function () {
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
        ->format(metalRowMajor())
        ->create();

    expect($buffer)->toBeInstanceOf(MetalHandledFramebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebuffer::class)
        ->and($manager->kindOf('metal'))->toBe(FramebufferKind::DEFERRED);
});

test('setPixel and flush round-trip on headless MTLTexture', function () {
    $buffer = MetalHandledFramebuffer::sized(4, 2, metalRowMajor());
    $buffer->setPixel(1, 0, 0xFF0000FF);

    expect($buffer->getPixel(1, 0))->toBe(0xFF0000FF);

    $frames = $buffer->flush(metalRowMajor(), as_array: true);

    expect($frames)->toHaveCount(1)
        ->and($frames[0]->render_type)->toBe(RenderType::PARTIAL)
        ->and($frames[0]->origin_x)->toBe(1)
        ->and($frames[0]->origin_y)->toBe(0)
        ->and(strlen($frames[0]->raw_data))->toBe(4)
        ->and(bin2hex($frames[0]->raw_data))->toBe('ff0000ff');
});

test('markAllDirty flush emits FULL surface bytes', function () {
    $buffer = MetalHandledFramebuffer::sized(4, 2, metalRowMajor());
    $buffer->fill(0x112233FF);
    $bytes = $buffer->flush(metalRowMajor());

    expect($bytes)->toBeString()
        ->and(strlen($bytes))->toBe(4 * 2 * 4);
});

test('flush to RGB565 packs RGBA words correctly and emits PARTIAL for local dirty', function () {
    $buffer = MetalHandledFramebuffer::sized(8, 8, metalRowMajor());
    $buffer->fill(0x000000FF);
    $buffer->flush(metalRowMajor(), as_array: true); // clear prime dirty

    $rgb565 = new FormatSpec(
        PixelFormat::ROW_MAJOR,
        BitDepth::B16,
        endianness: Endianness::MSB,
    );

    $buffer->setSegment(2, 3, 3, 2, 0xFF0000FF); // pure red → 0xF800
    $frames = $buffer->flush($rgb565, as_array: true);

    expect($frames)->toHaveCount(1)
        ->and($frames[0]->render_type)->toBe(RenderType::PARTIAL)
        ->and($frames[0]->origin_x)->toBe(2)
        ->and($frames[0]->origin_y)->toBe(3)
        ->and($frames[0]->width)->toBe(3)
        ->and($frames[0]->height)->toBe(2)
        ->and(bin2hex($frames[0]->raw_data))->toBe(str_repeat('f800', 6));
});

test('headless damageGranularity is pixel-perfect for PanelIC partial', function () {
    $buffer = MetalHandledFramebuffer::sized(16, 16, metalRowMajor());

    expect($buffer->damageGranularity()->coversWholeSurface())->toBeFalse()
        ->and($buffer->damageGranularity()->isPixelPerfect())->toBeTrue();
});

test('fill clears the offscreen MTLTexture', function () {
    $buffer = MetalHandledFramebuffer::sized(2, 2, metalRowMajor());
    $buffer->fill(0x00FF00FF);

    expect($buffer->getPixel(0, 0))->toBe(0x00FF00FF)
        ->and($buffer->getPixel(1, 1))->toBe(0x00FF00FF);
});

test('attachedTo rejects a zero window handle', function () {
    expect(fn () => MetalHandledFramebuffer::attachedTo(0, metalRowMajor(), 4, 4))
        ->toThrow(MetalGfxException::class);
});

test('attachedTo rejects a window with no attached device', function () {
    mtl_app_init();
    $window = mtl_window_create('no-device', 16, 16);
    expect($window)->toBeGreaterThan(0);

    expect(fn () => MetalHandledFramebuffer::attachedTo($window, metalRowMajor(), 16, 16))
        ->toThrow(MetalGfxException::class);

    mtl_window_destroy($window);
});

test('attachedTo binds a non-headless framebuffer to a real Metal window', function () {
    mtl_app_init();
    $device = mtl_device_create_system_default();
    $window = mtl_window_create('fb-attach', 32, 24);
    expect($device)->toBeGreaterThan(0)
        ->and($window)->toBeGreaterThan(0)
        ->and(mtl_window_attach_device($window, $device))->toBeTrue();

    $buffer = MetalHandledFramebuffer::attachedTo($window, metalRowMajor(), 32, 24);

    expect($buffer->isHeadless())->toBeFalse()
        ->and($buffer->metalWindow())->toBe($window)
        ->and($buffer->metalDevice())->toBe($device);

    $buffer->fill(0xAABBCCFF)->setPixel(2, 2, 0x11223344);
    $buffer->present();

    unset($buffer);
    mtl_window_destroy($window);
    mtl_device_release($device);
});
