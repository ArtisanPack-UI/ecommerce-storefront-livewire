<?php

/**
 * Toast concern for storefront components.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;

/**
 * Shows `<x-artisanpack-toast>` notices from a storefront component.
 *
 * livewire-ui-components ships a `Toast` trait, but its methods are public,
 * which makes each of them a Livewire action any client could call. These
 * helpers are protected, so they stay server-side.
 *
 * The toast container renders the title and description with Alpine's
 * `x-html`, so both are HTML-escaped ({@see ToastPayload}): they often
 * carry product names and engine messages, which must never run as markup
 * in a shopper's browser.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait SendsToasts
{
    /**
     * Shows a success toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastSuccess( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'success', $title, $description, 'alert-success' );
    }

    /**
     * Shows a warning toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastWarning( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'warning', $title, $description, 'alert-warning' );
    }

    /**
     * Shows an error toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastError( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'error', $title, $description, 'alert-error' );
    }

    /**
     * Shows a success toast on the next page the shopper sees, for actions
     * that reload the page (the currency switcher). The toast waits in the
     * session until `ecommerce-storefront::partials.global` shows it.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function flashToastSuccess( string $title, ?string $description = null ): void
    {
        session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'success', $title, $description, 'alert-success' ) );
    }

    /**
     * Shows a warning toast on the next page the shopper sees, for actions
     * that redirect (the checkout sending an empty cart back to the cart).
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function flashToastWarning( string $title, ?string $description = null ): void
    {
        session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'warning', $title, $description, 'alert-warning' ) );
    }

    /**
     * Dispatches the toast to the browser.
     *
     * @since 1.0.0
     *
     * @param  string       $type         The toast type.
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     * @param  string       $css          The alert class.
     *
     * @return void
     */
    private function sendToast( string $type, string $title, ?string $description, string $css ): void
    {
        $this->js( 'if ( typeof toast === "function" ) { toast( ' . ToastPayload::encode( ToastPayload::make( $type, $title, $description, $css ) ) . ' ); }' );
    }
}
