<?php

namespace Microscrap\GFX\Metal;

use ScrapyardIO\Tubes\Contracts\Framebuffers\FramebufferException;

class MetalGfxException extends FramebufferException
{
    public static function deviceCreationFailed(): static
    {
        return new static('Could not create the system-default MTLDevice for a headless MetalHandledFramebuffer.');
    }

    /**
     * @deprecated 0.7 window attach is implemented via MetalWindowHandler + attachedTo()
     */
    public static function windowAttachNotReady(): static
    {
        return new static(
            'MetalHandledFramebuffer::attachedTo() requires a valid window with an attached MTLDevice.'
        );
    }
}
