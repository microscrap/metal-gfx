<?php

namespace Microscrap\GFX\Metal\Providers;

use Fabricate\NutsAndBolts\ServiceProvider;
use Microscrap\GFX\Metal\MetalHandledFramebuffer;
use Microscrap\GFX\Metal\MetalWindowHandler;
use ScrapyardIO\Tubes\Contracts\Framebuffers\BufferFactory;
use ScrapyardIO\Tubes\Contracts\Windows\WindowFactory;
use ScrapyardIO\Tubes\Framebuffers\PendingFramebuffer;

class MetalGfxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->container->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/framebuffers/metal.php' => $this->container->configPath('framebuffers/metal.php'),
            ], 'tubes-framebuffers-metal');

            $this->publishes([
                __DIR__.'/../../config/windows/metal.php' => $this->container->configPath('windows/metal.php'),
            ], 'tubes-windows-metal');
        }

        $this->callAfterResolving('framebuffer', function (BufferFactory $framebuffers): void {
            $framebuffers->extendDeferred(
                'metal',
                fn (PendingFramebuffer $pending) => MetalHandledFramebuffer::sized(
                    $pending->widthValue(),
                    $pending->heightValue(),
                    $pending->hostFormatValue(),
                ),
            );
        });

        $this->callAfterResolving('window', function (WindowFactory $windows): void {
            $windows->extend('metal', MetalWindowHandler::class);
        });
    }
}
