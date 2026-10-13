<?php

/**
 * Error summary concern for storefront forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * Backs `ecommerce-storefront::partials.error-summary`: a counter bumped on
 * each submit keys the summary, so it is re-created (announced, and given
 * focus) after every failed submit, even with the same errors, and left
 * alone by other requests (a live country change, say).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait SummarizesErrors
{
    /**
     * Counts submits, to key the error summary.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $errorSummary = 0;

    /**
     * Starts a submit: any errors it adds get a new summary.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function newErrorSummary(): void
    {
        $this->errorSummary++;
    }
}
