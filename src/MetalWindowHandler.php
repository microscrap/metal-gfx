<?php

namespace Microscrap\GFX\Metal;

use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Windows\WindowException;
use ScrapyardIO\Tubes\Windows\WindowHandler;

/**
 * Metal OS window driver for tubes {@see \ScrapyardIO\Tubes\Canvas\OSWindow}.
 *
 * FormatSpec matches {@see MetalHandledFramebuffer::rgbaSpec()}.
 * Present path: offscreen MTLTexture → CAMetalLayer via mtl_window_present_texture (no PHP flush).
 */
class MetalWindowHandler extends WindowHandler
{
    protected static bool $app_initialized = false;

    protected static bool $menu_installed = false;

    protected int $device = 0;

    protected int $window = 0;

    protected MetalInputHandler $input_handler;

    protected bool $vsync = true;

    public function __construct(string $title, int $width, int $height)
    {
        parent::__construct($title, $width, $height);
        $this->input_handler = new MetalInputHandler($this);
    }

    /**
     * Companion Human Input driver — refreshed from {@see pollNative()}.
     */
    public function inputHandler(): MetalInputHandler
    {
        return $this->input_handler;
    }

    protected function defineFormatSpec(): FormatSpec
    {
        return MetalHandledFramebuffer::rgbaSpec();
    }

    protected function bootNative(): void
    {
        if (! static::$app_initialized) {
            if (! mtl_app_init()) {
                throw new WindowException('mtl_app_init() failed.');
            }
            mtl_app_reset_quit();
            static::$app_initialized = true;
        }

        if (! static::$menu_installed) {
            mtl_menu_install_default('Metal');
            static::$menu_installed = true;
        }

        $device = mtl_device_create_system_default();
        if ($device <= 0) {
            throw new WindowException('Could not create the system-default MTLDevice for MetalWindowHandler.');
        }

        $window = mtl_window_create($this->title, $this->width, $this->height);
        if ($window <= 0) {
            mtl_device_release($device);
            throw new WindowException("Could not create an NSWindow ({$this->width}x{$this->height}).");
        }

        if (! mtl_window_attach_device($window, $device)) {
            mtl_window_destroy($window);
            mtl_device_release($device);
            throw new WindowException('Could not attach MTLDevice to the Metal window.');
        }

        mtl_window_show($window);

        $this->device = $device;
        $this->window = $window;
        $this->setVsync($this->vsync);
    }

    protected function bindFramebuffer(): DeferredFramebuffer
    {
        if ($this->window <= 0) {
            throw new WindowException('MetalWindowHandler has no native window for bindFramebuffer().');
        }

        return MetalHandledFramebuffer::attachedTo(
            $this->window,
            $this->formatSpec(),
            $this->width(),
            $this->height(),
        );
    }

    protected function presentNative(): void
    {
        $framebuffer = $this->framebuffer();
        if (! $framebuffer instanceof MetalHandledFramebuffer) {
            throw new WindowException('MetalWindowHandler expected a MetalHandledFramebuffer.');
        }

        $framebuffer->present();
    }

    protected function pollNative(): void
    {
        mtl_app_poll();
        $action = mtl_menu_poll_action();
        if ($action === 'quit') {
            mtl_app_terminate();
        }

        // One AppKit pump → window close flags + HumanInput device snapshots.
        $this->input_handler->poll();
    }

    public function shouldClose(): bool
    {
        if ($this->window <= 0) {
            return true;
        }

        return mtl_window_should_close($this->window) || mtl_app_should_quit();
    }

    /**
     * Release framebuffer GPU resources before destroying the window/device.
     */
    public function close(): static
    {
        if (! $this->opened) {
            return $this;
        }

        $this->framebuffer = null;
        $this->destroyNative();
        $this->opened = false;

        return $this;
    }

    protected function destroyNative(): void
    {
        if ($this->window > 0) {
            mtl_window_destroy($this->window);
            $this->window = 0;
        }

        if ($this->device > 0) {
            mtl_device_release($this->device);
            $this->device = 0;
        }
    }

    public function metalWindow(): int
    {
        return $this->window;
    }

    public function metalDevice(): int
    {
        return $this->device;
    }

    /**
     * CAMetalLayer display sync when ext-metal exposes {@see mtl_window_set_display_sync()}.
     */
    public function setVsync(bool $on): static
    {
        $this->vsync = $on;

        if ($this->window > 0 && function_exists('mtl_window_set_display_sync')) {
            mtl_window_set_display_sync($this->window, $on);
        }

        return $this;
    }

    public function vsync(): bool
    {
        return $this->vsync;
    }
}
