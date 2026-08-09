<?php

use Microscrap\GFX\Metal\MetalInputHandler;
use Microscrap\GFX\Metal\MetalWindowHandler;
use ScrapyardIO\Tubes\HumanInput\EngineInput;
use ScrapyardIO\Tubes\HumanInput\Enums\MouseButton;
use ScrapyardIO\Tubes\Inputs\InputHandler;

beforeEach(function (): void {
    if (! extension_loaded('metal')) {
        $this->markTestSkipped('ext-metal is not loaded');
    }

    if (! function_exists('mtl_input_key_down')) {
        $this->markTestSkipped('microscrap/metal input helpers are not loaded');
    }
});

test('MetalInputHandler extends InputHandler and exposes empty device snapshots', function () {
    $handler = new MetalInputHandler;

    expect($handler)->toBeInstanceOf(InputHandler::class)
        ->and($handler->keyboard())->not->toBeNull()
        ->and($handler->mouse())->not->toBeNull()
        ->and($handler->gamePads())->toBe([])
        ->and($handler->gameControllers())->toBe([]);
});

test('MetalInputHandler poll snapshots mouse buttons and keyboard map', function () {
    mtl_app_init();
    $handler = new MetalInputHandler;
    $handler->poll();

    expect($handler->keyboard()->keys())->not->toBeEmpty()
        ->and($handler->mouse()->button(MouseButton::LEFT))->not->toBeNull()
        ->and($handler->mouse()->button(MouseButton::RIGHT))->not->toBeNull()
        ->and($handler->mouse()->button(MouseButton::MIDDLE))->not->toBeNull();

    $pos = $handler->mouse()->position();
    expect($pos)->toHaveCount(2)
        ->and($pos[0])->toBeFloat()
        ->and($pos[1])->toBeFloat();
});

test('MetalWindowHandler pollEvents fans out into MetalInputHandler', function () {
    $window = new MetalWindowHandler('metal-input-fanout', 96, 64);
    $window->open();

    $input = $window->inputHandler();
    expect($input)->toBeInstanceOf(MetalInputHandler::class)
        ->and($input->windowHandler())->toBe($window);

    $engine = new EngineInput($input);
    $window->pollEvents();

    expect($engine->poll())->toBe($engine)
        ->and($engine->mouse())->not->toBeNull()
        ->and($engine->keyboard())->not->toBeNull();

    // Window-relative mouse Y is flipped to framebuffer top-left space.
    $y = $engine->mouse()->y();
    expect($y)->toBeGreaterThanOrEqual(0.0);

    $window->close();
});
