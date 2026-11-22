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
 * Shows `<x-artisanpack-toaster>` notices from a storefront component.
 *
 * livewire-ui-components ships a `Toast` trait, but its methods are public,
 * which makes each of them a Livewire action any client could call. These
 * helpers are protected, so they stay server-side.
 *
 * The toaster renders the title and description as text ({@see ToastPayload}):
 * they often carry product names and engine messages, which must never run
 * as markup in a shopper's browser. Since 2.0.0 each helper takes the
 * design's options (#97): the inverse variant, an icon, and one action,
 * such as Undo calling a method on this component.
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
     * @since 2.0.0 Added `$options`.
     *
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    protected function toastSuccess( string $title, ?string $description = null, array $options = [] ): void
    {
        $this->sendToast( 'success', $title, $description, $options );
    }

    /**
     * Shows a warning toast.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$options`.
     *
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    protected function toastWarning( string $title, ?string $description = null, array $options = [] ): void
    {
        $this->sendToast( 'warning', $title, $description, $options );
    }

    /**
     * Shows an error toast.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$options`.
     *
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    protected function toastError( string $title, ?string $description = null, array $options = [] ): void
    {
        $this->sendToast( 'error', $title, $description, $options );
    }

    /**
     * Shows a success toast on the next page the shopper sees, for actions
     * that reload the page (the currency switcher). The toast waits in the
     * session until `ecommerce-storefront::partials.global` shows it.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$options`.
     *
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    protected function flashToastSuccess( string $title, ?string $description = null, array $options = [] ): void
    {
        session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'success', $title, $description, $options ) );
    }

    /**
     * Shows a warning toast on the next page the shopper sees, for actions
     * that redirect (the checkout sending an empty cart back to the cart).
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$options`.
     *
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    protected function flashToastWarning( string $title, ?string $description = null, array $options = [] ): void
    {
        session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'warning', $title, $description, $options ) );
    }

    /**
     * Dispatches the toast to the browser.
     *
     * @since 1.0.0
     *
     * @param  string                $type         The toast type.
     * @param  string                $title        The title.
     * @param  string|null           $description  The description.
     * @param  array<string, mixed>  $options      The variant, icon and action ({@see ToastPayload::make()}).
     *
     * @return void
     */
    private function sendToast( string $type, string $title, ?string $description, array $options = [] ): void
    {
        $this->js( ToastPayload::script( ToastPayload::make( $type, $title, $description, $options ), true ) );
    }
}
