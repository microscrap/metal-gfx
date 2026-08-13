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
    /** @var list<KeyCode>|null */
    private ?array $keyCodes = null;

    /** @var list<GamepadButton>|null */
    private ?array $gamepadButtons = null;

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
        $this->keyCodes ??= KeyCode::cases();
        foreach ($this->keyCodes as $code) {
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

        if (function_exists('mtl_input_mouse_x')) {
            $x = (float) mtl_input_mouse_x($window);
            $y = (float) mtl_input_mouse_y($window);
            if ($window > 0 && $height > 0.0) {
                $y = $height - $y;
            }
            $wheel = (float) mtl_input_mouse_scroll_y();
        } else {
            $x = 0.0;
            $y = 0.0;
            $wheel = 0.0;
            $existing = $this->mouse;
            if (! is_null($existing)) {
                $x = (float) $existing->x();
                $y = (float) $existing->y();
                $wheel = (float) $existing->wheelDelta();
            }
        }

        $leftDown = mtl_input_mouse_button_down(MetalMouseButton::LEFT);
        $rightDown = mtl_input_mouse_button_down(MetalMouseButton::RIGHT);
        $middleDown = mtl_input_mouse_button_down(MetalMouseButton::MIDDLE);

        $mouse = $this->mouse;
        if (is_null($mouse)) {
            $this->mouse = new Mouse($x, $y, [
                new DigitalButton(MouseButton::LEFT->value, $leftDown),
                new DigitalButton(MouseButton::RIGHT->value, $rightDown),
                new DigitalButton(MouseButton::MIDDLE->value, $middleDown),
            ], $wheel);

            return;
        }

        $mouse->setPosition($x, $y)->setWheelDelta($wheel);
        foreach ($mouse->buttons() as $button) {
            match ($button->name()) {
                MouseButton::LEFT->value => $button->setPressed($leftDown),
                MouseButton::RIGHT->value => $button->setPressed($rightDown),
                MouseButton::MIDDLE->value => $button->setPressed($middleDown),
                default => null,
            };
        }
    }

    protected function refreshGameControllers(): void
    {
        $count = mtl_input_gamepad_count();
        if ($count <= 0) {
            return;
        }

        if (count($this->game_controllers) !== $count) {
            $this->rebuildGameControllers($count);

            return;
        }

        $this->updateGameControllers($count);
    }

    protected function rebuildGameControllers(int $count): void
    {
        $this->gamepadButtons ??= GamepadButton::cases();
        $controllers = [];

        for ($index = 0; $index < $count; $index++) {
            $name = mtl_input_gamepad_name($index);
            if ($name === '') {
                $name = "Gamepad {$index}";
            }

            $controls = [];

            foreach ($this->gamepadButtons as $button) {
                $controls[] = new DigitalButton(
                    $button->name,
                    mtl_input_gamepad_button_down($index, $button),
                );
            }

            $controls[] = new AnalogButton(
                GamepadAxis::LEFT_TRIGGER->name,
                $this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_TRIGGER)),
            );
            $controls[] = new AnalogButton(
                GamepadAxis::RIGHT_TRIGGER->name,
                $this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_TRIGGER)),
            );
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
    }

    protected function updateGameControllers(int $count): void
    {
        $this->gamepadButtons ??= GamepadButton::cases();

        foreach ($this->game_controllers as $index => $controller) {
            if ($index >= $count) {
                break;
            }

            foreach ($controller->digitalButtons() as $i => $button) {
                $enum = $this->gamepadButtons[$i] ?? null;
                if (is_null($enum)) {
                    continue;
                }
                $button->setPressed(mtl_input_gamepad_button_down($index, $enum));
            }

            $analogs = $controller->analogButtons();
            if (isset($analogs[0])) {
                $analogs[0]->setValue($this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_TRIGGER)));
            }
            if (isset($analogs[1])) {
                $analogs[1]->setValue($this->clamp01(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_TRIGGER)));
            }

            $sticks = $controller->sticks();
            if (isset($sticks[0])) {
                $sticks[0]->setAxes(
                    $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_X)),
                    $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::LEFT_Y)),
                );
            }
            if (isset($sticks[1])) {
                $sticks[1]->setAxes(
                    $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_X)),
                    $this->clampAxis(mtl_input_gamepad_axis($index, GamepadAxis::RIGHT_Y)),
                );
            }
        }
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
