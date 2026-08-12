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
use ScrapyardIO\Tubes\Framebuffers\PixelStore;

/**
 * Deferred Metal-handled framebuffer.
 *
 * Headless {@see sized()}: owns MTLDevice + queue + offscreen RGBA8 MTLTexture.
 * Windowed {@see attachedTo()}: borrows the window's MTLDevice; owns queue + texture;
 * {@see present()} blits the texture to the CAMetalLayer (no PHP flush remux).
 *
 * Headless PanelIC: dirty rects → {@see RenderType::PARTIAL} dumps; host RGBA
 * words pack to IC FormatSpec (fast ROW_MAJOR B16, not per-pixel PixelStore).
 * Flush shares one {@see mtl_texture_read_rgba8()} across regions.
 */
class MetalHandledFramebuffer extends DeferredFramebuffer
{
    protected int $device = 0;

    protected int $queue = 0;

    protected int $texture = 0;

    protected int $window = 0;

    protected bool $owns_device = true;

    /**
     * Inclusive dirty rectangles [left, top, right, bottom] — coalesced at flush.
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    protected array $dirty_regions = [];

    /**
     * When > 0, markDirty unions into {@see $deferred_dirty_union} instead of
     * appending one rect per primitive (fillCircle hlines thrash coalesce).
     */
    protected int $dirty_defer_depth = 0;

    /**
     * Inclusive union while {@see deferDirty()} is active.
     *
     * @var array{0: int, 1: int, 2: int, 3: int}|null
     */
    protected ?array $deferred_dirty_union = null;

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
        $this->markAllDirty();

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
        $this->markDirty($x, $y, $x, $y);

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
        $cw = $x1 - $x0;
        $ch = $y1 - $y0;

        if ($cw <= 0 || $ch <= 0) {
            return $this;
        }

        [$r, $g, $b, $a] = $this->rgbaComponents($color);
        if (! mtl_texture_fill_rect($this->texture, $x0, $y0, $cw, $ch, $r, $g, $b, $a)) {
            throw new MetalGfxException('mtl_texture_fill_rect() failed for MetalHandledFramebuffer::setSegment.');
        }
        $this->markDirty($x0, $y0, $x1 - 1, $y1 - 1);

        return $this;
    }

    public function dump(?int $layer = null): string
    {
        return $this->packRgbaWords($this->readTextureWords(), $this->width, $this->height, $this->host_format);
    }

    /**
     * Flush for PanelIC (headless) — host RGBA8 MTLTexture → target FormatSpec.
     *
     * Matching B32 host → chunked pack. ROW_MAJOR B16 (ST7789 RGB565) uses a
     * tight pack loop — not per-pixel {@see PixelStore} / low-16-bits-as-RGB565.
     * Dirty rects emit {@see RenderType::PARTIAL} when damage is sparse.
     * One {@see mtl_texture_read_rgba8()} per flush, shared across regions.
     *
     * @return string|array<int, DumpedBuffer|int>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array
    {
        if (! $this->isHeadless()) {
            $this->present();

            return $as_array ? [] : '';
        }

        if ($this->dirty_regions === []) {
            return $as_array ? [] : '';
        }

        $regions = $this->coalesceDirtyRegions($this->dirty_regions);
        $this->dirty_regions = [];

        $whole = count($regions) === 1
            && $regions[0][0] === 0
            && $regions[0][1] === 0
            && $regions[0][2] === ($this->width - 1)
            && $regions[0][3] === ($this->height - 1);

        // One full GPU read shared across FULL / PARTIAL packs in this flush.
        $fullWords = $this->readTextureWords();

        if ($whole) {
            $bytes = $this->packRgbaWords($fullWords, $this->width, $this->height, $spec);

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

        $updates = [];

        foreach ($regions as [$left, $top, $right, $bottom]) {
            $regionW = ($right - $left) + 1;
            $regionH = ($bottom - $top) + 1;
            $words = $this->sliceWords($fullWords, $left, $top, $regionW, $regionH);
            $bytes = $this->packRgbaWords($words, $regionW, $regionH, $spec);

            $updates[] = new DumpedBuffer(
                RenderType::PARTIAL,
                $spec,
                $bytes,
                origin_x: $left,
                origin_y: $top,
                width: $regionW,
                height: $regionH,
            );
        }

        if (! $as_array) {
            $joined = '';
            foreach ($updates as $frame) {
                $joined .= $frame->raw_data;
            }

            return $joined;
        }

        return $updates;
    }

    /**
     * Collapse many primitive dirty marks into one bbox (circles / text runs).
     *
     * @param  callable(): void  $draw
     */
    public function deferDirty(callable $draw): static
    {
        $this->dirty_defer_depth++;

        try {
            $draw();
        } finally {
            $this->dirty_defer_depth--;

            if ($this->dirty_defer_depth === 0 && ! is_null($this->deferred_dirty_union)) {
                [$left, $top, $right, $bottom] = $this->deferred_dirty_union;
                $this->deferred_dirty_union = null;
                $this->markDirty($left, $top, $right, $bottom);
            }
        }

        return $this;
    }

    public function damageGranularity(): DamageGranularity
    {
        // Headless PanelIC path tracks dirty rects → pixel damage (PARTIAL flush).
        // Window-attached surfaces stay whole-surface (native present, no PHP dump).
        if ($this->isHeadless()) {
            return DamageGranularity::pixel($this->width, $this->height);
        }

        return DamageGranularity::wholeSurface($this->width, $this->height);
    }

    public function preservesContentsOnPresent(): bool
    {
        // Offscreen MTLTexture survives present blit; headless PanelIC can PARTIAL.
        return $this->isHeadless();
    }

    public function markAllDirty(): static
    {
        $this->deferred_dirty_union = null;
        $this->dirty_regions = [[0, 0, $this->width - 1, $this->height - 1]];

        return $this;
    }

    protected function markDirty(int $left, int $top, int $right, int $bottom): void
    {
        $left = max(0, $left);
        $top = max(0, $top);
        $right = min($this->width - 1, $right);
        $bottom = min($this->height - 1, $bottom);

        if (($left > $right) || ($top > $bottom)) {
            return;
        }

        if ($this->dirty_defer_depth > 0) {
            if (is_null($this->deferred_dirty_union)) {
                $this->deferred_dirty_union = [$left, $top, $right, $bottom];

                return;
            }

            $this->deferred_dirty_union[0] = min($this->deferred_dirty_union[0], $left);
            $this->deferred_dirty_union[1] = min($this->deferred_dirty_union[1], $top);
            $this->deferred_dirty_union[2] = max($this->deferred_dirty_union[2], $right);
            $this->deferred_dirty_union[3] = max($this->deferred_dirty_union[3], $bottom);

            return;
        }

        $this->dirty_regions[] = [$left, $top, $right, $bottom];
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int, 3: int}>  $regions
     * @return array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    protected function coalesceDirtyRegions(array $regions): array
    {
        if ($regions === []) {
            return [];
        }

        $pending = array_values($regions);
        $merged = [];

        while ($pending !== []) {
            [$left, $top, $right, $bottom] = array_shift($pending);
            $grew = true;

            while ($grew) {
                $grew = false;
                $next = [];

                foreach ($pending as $region) {
                    [$region_left, $region_top, $region_right, $region_bottom] = $region;
                    $touches = ($left <= $region_right + 1) && ($region_left <= $right + 1)
                        && ($top <= $region_bottom + 1) && ($region_top <= $bottom + 1);

                    if ($touches) {
                        $left = min($left, $region_left);
                        $top = min($top, $region_top);
                        $right = max($right, $region_right);
                        $bottom = max($bottom, $region_bottom);
                        $grew = true;
                    } else {
                        $next[] = $region;
                    }
                }

                $pending = $next;
            }

            $merged[] = [$left, $top, $right, $bottom];
        }

        return $merged;
    }

    /**
     * Full-surface host words (0xRRGGBBAA) from one GPU readback.
     *
     * @return array<int, int>
     */
    protected function readTextureWords(): array
    {
        $bytes = mtl_texture_read_rgba8($this->texture, $this->queue);
        if ($bytes === '') {
            return array_fill(0, max(0, $this->width * $this->height), 0);
        }

        $words = unpack('N*', $bytes);
        if ($words === false) {
            return array_fill(0, max(0, $this->width * $this->height), 0);
        }

        return array_values($words);
    }

    /**
     * @param  array<int, int>  $fullWords
     * @return array<int, int>
     */
    protected function sliceWords(array $fullWords, int $x, int $y, int $width, int $height): array
    {
        $sliced = [];

        for ($row = 0; $row < $height; $row++) {
            $src = (($y + $row) * $this->width) + $x;
            for ($col = 0; $col < $width; $col++) {
                $sliced[] = $fullWords[$src + $col] ?? 0;
            }
        }

        return $sliced;
    }

    /**
     * Pack host RGBA words (0xRRGGBBAA) into a target FormatSpec byte stream.
     *
     * Matching B32 MSB → chunked pack. ROW_MAJOR B16 → tight RGB565 (not
     * low-16-bits-as-RGB565) — avoid per-pixel PixelStore on the PanelIC hot path.
     *
     * @param  array<int, int>  $words
     */
    protected function packRgbaWords(array $words, int $width, int $height, FormatSpec $spec): string
    {
        if (
            $spec->pixel_format === PixelFormat::ROW_MAJOR
            && $spec->bit_depth === BitDepth::B32
            && ($spec->endianness ?? Endianness::MSB) === Endianness::MSB
        ) {
            return $this->packWordChunks($words, 'N*');
        }

        if (
            $spec->pixel_format === PixelFormat::ROW_MAJOR
            && $spec->bit_depth === BitDepth::B16
        ) {
            $msb = ($spec->endianness ?? Endianness::MSB) !== Endianness::LSB;
            $packed = [];

            foreach ($words as $word) {
                $r = ($word >> 24) & 0xFF;
                $g = ($word >> 16) & 0xFF;
                $b = ($word >> 8) & 0xFF;
                $packed[] = (($r & 0xF8) << 8) | (($g & 0xFC) << 3) | ($b >> 3);
            }

            return $this->packWordChunks($packed, $msb ? 'n*' : 'v*');
        }

        $temp = new PixelStore($width, $height, $spec, 1);
        $i = 0;

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                $temp->setPixel($col, $row, $words[$i] ?? 0);
                $i++;
            }
        }

        return $temp->dump();
    }

    /**
     * @param  array<int, int>  $words
     */
    protected function packWordChunks(array $words, string $format): string
    {
        if ($words === []) {
            return '';
        }

        $bytes = '';
        $chunkSize = 512;

        for ($offset = 0, $count = count($words); $offset < $count; $offset += $chunkSize) {
            $chunk = array_slice($words, $offset, $chunkSize);
            $bytes .= pack($format, ...$chunk);
        }

        return $bytes;
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
}
