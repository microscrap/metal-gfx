<?php

namespace Microscrap\GFX\Metal;

use ScrapyardIO\Tubes\Contracts\Framebuffers\DamageGranularity;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DumpedBuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\Endianness;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\RenderType;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer;

/**
 * Deferred Metal-handled framebuffer.
 *
 * Headless {@see sized()}: owns MTLDevice + queue + offscreen RGBA8 MTLTexture.
 * Windowed {@see attachedTo()}: borrows the window's MTLDevice; owns queue + texture;
 * {@see present()} blits the texture to the CAMetalLayer (no PHP flush remux).
 */
class MetalHandledFramebuffer extends DeferredFramebuffer
{
    protected int $device = 0;

    protected int $queue = 0;

    protected int $texture = 0;

    protected int $window = 0;

    protected bool $owns_device = true;

    /**
     * @throws MetalGfxException
     */
    public function __construct(
        int $width,
        int $height,
        FormatSpec $format_spec,
        ?int $device = null,
        ?int $queue = null,
        ?int $texture = null,
        int $window = 0,
        bool $owns_device = true,
    ) {
        parent::__construct($width, $height, $format_spec);

        if ($width <= 0 || $height <= 0) {
            throw new MetalGfxException("MetalHandledFramebuffer size must be positive, got {$width}x{$height}.");
        }

        $created_device = is_null($device);
        if ($created_device) {
            $device = mtl_device_create_system_default();
            $owns_device = true;
        }
        if ($device <= 0) {
            throw MetalGfxException::deviceCreationFailed();
        }

        $owns_queue = is_null($queue);
        if ($owns_queue) {
            $queue = mtl_device_new_command_queue($device);
        }
        if ($queue <= 0) {
            if ($created_device || $owns_device) {
                mtl_device_release($device);
            }
            throw new MetalGfxException('Could not create an MTLCommandQueue for MetalHandledFramebuffer.');
        }

        $owns_texture = is_null($texture);
        if ($owns_texture) {
            $texture = mtl_texture_create_rgba8($device, $width, $height);
        }
        if ($texture <= 0) {
            if ($owns_queue) {
                mtl_command_queue_release($queue);
            }
            if ($created_device || $owns_device) {
                mtl_device_release($device);
            }
            throw new MetalGfxException("Could not create a {$width}x{$height} offscreen MTLTexture.");
        }

        $this->device = $device;
        $this->queue = $queue;
        $this->texture = $texture;
        $this->window = $window;
        $this->owns_device = $owns_device;
    }

    public function __destruct()
    {
        if ($this->texture > 0) {
            mtl_texture_release($this->texture);
            $this->texture = 0;
        }
        if ($this->queue > 0) {
            mtl_command_queue_release($this->queue);
            $this->queue = 0;
        }
        if ($this->owns_device && $this->device > 0) {
            mtl_device_release($this->device);
            $this->device = 0;
        }
        $this->window = 0;
    }

    /**
     * Headless factory: device + queue + offscreen texture (no window).
     *
     * @throws MetalGfxException
     */
    public static function sized(int $width, int $height, FormatSpec $host_format): static
    {
        return new static($width, $height, $host_format);
    }

    /**
     * Window-bound factory: same MTLDevice as the NSWindow; offscreen RGBA8 texture for draw;
     * {@see present()} blits to the CAMetalLayer.
     *
     * @throws MetalGfxException
     */
    public static function attachedTo(int $window, FormatSpec $format_spec, int $width, int $height): static
    {
        if ($window <= 0) {
            throw new MetalGfxException('MetalHandledFramebuffer::attachedTo() requires a valid Metal window handle.');
        }

        $device = mtl_window_get_device($window);
        if ($device <= 0) {
            throw new MetalGfxException(
                'MetalHandledFramebuffer::attachedTo() requires mtl_window_attach_device() first (no device on window).'
            );
        }

        return new static(
            $width,
            $height,
            $format_spec,
            device: $device,
            window: $window,
            owns_device: false,
        );
    }

    public static function rgbaSpec(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, endianness: Endianness::MSB);
    }

    public function metalDevice(): int
    {
        return $this->device;
    }

    public function metalQueue(): int
    {
        return $this->queue;
    }

    public function metalTexture(): int
    {
        return $this->texture;
    }

    public function metalWindow(): int
    {
        return $this->window;
    }

    public function metalDeviceName(): string
    {
        return $this->device > 0 ? mtl_device_get_name($this->device) : '';
    }

    public function isHeadless(): bool
    {
        return $this->window <= 0;
    }

    /**
     * Engine clear of the offscreen MTLTexture.
     */
    public function fill(int $color): static
    {
        [$r, $g, $b, $a] = $this->rgbaComponents($color);
        mtl_texture_clear($this->texture, $this->queue, $r, $g, $b, $a);

        return $this;
    }

    /**
     * Headless: no-op. Windowed: blit texture → CAMetalLayer drawable (no PHP flush).
     */
    public function present(): static
    {
        if ($this->isHeadless()) {
            return $this;
        }

        if (! mtl_window_present_texture($this->window, $this->texture)) {
            throw new MetalGfxException('mtl_window_present_texture() failed for MetalHandledFramebuffer.');
        }

        return $this;
    }

    public function getPixel(int $x, int $y): int
    {
        if (($x < 0) || ($y < 0) || ($x >= $this->width) || ($y >= $this->height)) {
            return 0;
        }

        $rgba = mtl_texture_read_pixel($this->texture, $this->queue, $x, $y);
        if ($rgba === []) {
            return 0;
        }

        return $this->packRgba($rgba[0], $rgba[1], $rgba[2], $rgba[3]);
    }

    public function setPixel(int $x, int $y, int $value): static
    {
        if (($x < 0) || ($y < 0) || ($x >= $this->width) || ($y >= $this->height)) {
            return $this;
        }

        [$r, $g, $b, $a] = $this->rgbaComponents($value);
        mtl_texture_write_pixel($this->texture, $x, $y, $r, $g, $b, $a);

        return $this;
    }

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static
    {
        if (($width <= 0) || ($height <= 0)) {
            return $this;
        }

        $x1 = min($this->width, $x + $width);
        $y1 = min($this->height, $y + $height);
        $x0 = max(0, $x);
        $y0 = max(0, $y);

        for ($py = $y0; $py < $y1; $py++) {
            for ($px = $x0; $px < $x1; $px++) {
                $this->setPixel($px, $py, $color);
            }
        }

        return $this;
    }

    public function dump(?int $layer = null): string
    {
        return mtl_texture_read_rgba8($this->texture, $this->queue);
    }

    /**
     * @return string|array<int, DumpedBuffer|int>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array
    {
        $bytes = mtl_texture_read_rgba8($this->texture, $this->queue);
        if (
            ! (
                $spec->bit_depth === BitDepth::B32
                && $spec->pixel_format === PixelFormat::ROW_MAJOR
                && ($spec->endianness ?? Endianness::MSB) === Endianness::MSB
            )
        ) {
            $bytes = $this->packViaPixels($spec);
        }

        if (! $as_array) {
            return $bytes;
        }

        return [
            new DumpedBuffer(
                RenderType::FULL,
                $spec,
                $bytes,
                width: $this->width,
                height: $this->height,
            ),
        ];
    }

    public function damageGranularity(): DamageGranularity
    {
        return DamageGranularity::wholeSurface($this->width, $this->height);
    }

    public function preservesContentsOnPresent(): bool
    {
        return true;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function rgbaComponents(int $color): array
    {
        if ($this->isMonochrome()) {
            return ($color === 0) ? [0, 0, 0, 255] : [255, 255, 255, 255];
        }

        return match ($this->host_format->bit_depth) {
            BitDepth::B8 => [$color & 0xFF, $color & 0xFF, $color & 0xFF, 255],
            BitDepth::B32 => [
                ($color >> 24) & 0xFF,
                ($color >> 16) & 0xFF,
                ($color >> 8) & 0xFF,
                $color & 0xFF,
            ],
            default => [
                ($color >> 16) & 0xFF,
                ($color >> 8) & 0xFF,
                $color & 0xFF,
                255,
            ],
        };
    }

    protected function packRgba(int $r, int $g, int $b, int $a): int
    {
        if ($this->isMonochrome()) {
            return (($r > 127) || ($g > 127) || ($b > 127)) ? 1 : 0;
        }

        return match ($this->host_format->bit_depth) {
            BitDepth::B8 => $r,
            BitDepth::B32 => ($r << 24) | ($g << 16) | ($b << 8) | $a,
            default => ($r << 16) | ($g << 8) | $b,
        };
    }

    protected function isMonochrome(): bool
    {
        return ($this->host_format->bit_depth === BitDepth::B1)
            || ($this->host_format->pixel_format === PixelFormat::MONO_VERTICAL_PAGE)
            || ($this->host_format->pixel_format === PixelFormat::MONO_HORIZONTAL);
    }

    protected function packViaPixels(FormatSpec $spec): string
    {
        $bytes = '';
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $color = $this->getPixel($x, $y);
                $bytes .= match ($spec->bit_depth) {
                    BitDepth::B8 => chr($color & 0xFF),
                    BitDepth::B16 => (($spec->endianness ?? Endianness::MSB) === Endianness::LSB)
                        ? chr($color & 0xFF).chr(($color >> 8) & 0xFF)
                        : chr(($color >> 8) & 0xFF).chr($color & 0xFF),
                    BitDepth::B32 => chr(($color >> 24) & 0xFF)
                        .chr(($color >> 16) & 0xFF)
                        .chr(($color >> 8) & 0xFF)
                        .chr($color & 0xFF),
                    default => chr(($color >> 16) & 0xFF).chr(($color >> 8) & 0xFF).chr($color & 0xFF),
                };
            }
        }

        return $bytes;
    }
}
