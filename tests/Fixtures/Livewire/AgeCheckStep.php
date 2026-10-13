<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

/**
 * A satellite checkout step: asks for a date of birth and reports the step
 * done.
 */
class AgeCheckStep extends Component
{
    public string $step = '';

    public string $dateOfBirth = '';

    public function confirmAge(): void
    {
        $this->dispatch( 'ecommerce-checkout-step-completed', step: $this->step );
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div data-age-check="{{ $step }}">
                <input type="date" wire:model="dateOfBirth">
            </div>
        BLADE;
    }
}
