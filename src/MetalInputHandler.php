<?php

namespace Microscrap\GFX\Metal;

use Microscrap\Bindings\Metal\Enums\GamepadAxis;
use Microscrap\Bindings\Metal\Enums\GamepadButton;
use Microscrap\Bindings\Metal\Enums\KeyCode;
use Microscrap\Bindings\Metal\Enums\MouseButton as MetalMouseButton;
use ScrapyardIO\Tubes\HumanInput\AnalogButton;
use ScrapyardIO\Tubes\HumanInput\AnalogStick;
use ScrapyardIO\Tubes\HumanInput\DigitalButton;
use ScrapyardIO\Tubes\HumanInput\Enums\MouseButton;
use ScrapyardIO\Tubes\HumanInput\GameController;
use ScrapyardIO\Tubes\HumanInput\Keyboard;
use ScrapyardIO\Tubes\HumanInput\Mouse;
use ScrapyardIO\Tubes\Inputs\InputHandler;

/**
 * Metal engine input companion — snapshots {@see mtl_input_*} into tubes devices.
 *
 * Does **not** call {@see mtl_app_poll()}; {@see MetalWindowHandler::pollNative()} owns
 * the AppKit pump and fans out into {@see poll()} afterward.
 */
class MetalInputHandler extends InputHandler
{
    public function __construct(
        protected ?MetalWindowHandler $window_handler = null,
    ) {
        $this->keyboard = new Keyboard;
        $this->mouse = new Mouse(
            buttons: [
                new DigitalButton(MouseButton::LEFT->value),
                new DigitalButton(MouseButton::RIGHT->value),
                new DigitalButton(MouseButton::MIDDLE->value),
            ],
        );
    }

    public function attachWindow(MetalWindowHandler $window_handler): static
    {
        $this->window_handler = $window_handler;

        return $this;
    }

    public function windowHandler(): ?MetalWindowHandler
    {
        return $this->window_handler;
    }

    public function poll(): static
    {
        $this->refreshKeyboard();
        $this->refreshMouse();
        $this->refreshGameControllers();

        return $this;
    }

    protected function refreshKeyboard(): void
    {
        $keyboard = $this->keyboard ?? new Keyboard;
        foreach (KeyCode::cases() as $code) {
            $keyboard->setKey($code->name, mtl_input_key_down($code));
        }
        $this->keyboard = $keyboard;
    }

    protected function refreshMouse(): void
    {
        $window = 0;
        $height = 0.0;
        if (! is_null($this->window_handler) && $this->window_handler->metalWindow() > 0) {
            $window = $this->window_handler->metalWindow();
            $height = (float) $this->window_handler->height();
        }

        $pos = mtl_input_mouse_position($window);
        $x = (float) ($pos[0] ?? 0.0);
        $y = (float) ($pos[1] ?? 0.0);

        // AppKit content Y is up; tubes framebuffer coords are top-left / Y down.
        if ($window > 0 && $height > 0.0) {
            $y = $height - $y;
        }

        $scroll = mtl_input_mouse_scroll_delta();
        $wheel = (float) ($scroll[1] ?? 0.0);

        $buttons = [
            new DigitalButton(
                MouseButton::LEFT->value,
                mtl_input_mouse_button_down(MetalMouseButton::LEFT),
            ),
            new DigitalButton(
                MouseButton::RIGHT->value,
                mtl_input_mouse_button_down(MetalMouseButton::RIGHT),
            ),
            new DigitalButton(
                MouseButton::MIDDLE->value,
                mtl_input_mouse_button_down(MetalMouseButton::MIDDLE),
            ),
        ];

        $this->mouse = new Mouse($x, $y, $buttons, $wheel);
    }

    protected function refreshGameControllers(): void
    {
        $controllers = [];
        $count = mtl_input_gamepad_count();

        for ($index = 0; $index < $count; $index++) {
            $name = mtl_input_gamepad_name($index);
            if ($name === '') {
                $name = "Gamepad {$index}";
            }

            $controls = [];

            foreach (GamepadButton::cases() as $button) {
                $controls[] = new DigitalButton(
                    $button->name,
                    mtl_input_gamepad_button_down($index, $button),
                );
            }

            $left_trigger = $this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_TRIGGER));
            $right_trigger = $this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_TRIGGER));
            $controls[] = new AnalogButton(GamepadAxis::LEFT_TRIGGER->name, $left_trigger);
            $controls[] = new AnalogButton(GamepadAxis::RIGHT_TRIGGER->name, $right_trigger);

            $controls[] = new AnalogStick(
                'LEFT',
                $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_X)),
                $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_Y)),
            );
            $controls[] = new AnalogStick(
                'RIGHT',
                $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_X)),
                $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_Y)),
            );

            $controllers[] = new GameController($name, $controls);
        }

        $this->game_controllers = $controllers;
        $this->game_pads = [];
    }

    protected function clampAxis(float $value): float
    {
        if ($value < -1.0) {
            return -1.0;
        }
        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }

    protected function clamp01(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }
}
