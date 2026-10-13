<?php

/**
 * Product form registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Registries;

use ArtisanPackUI\Ecommerce\Contracts\ProvidesStorefrontOptions;
use ArtisanPackUI\Ecommerce\Models\Product;
use InvalidArgumentException;

/**
 * Maps a product type to the Livewire component that renders its purchase
 * form on the product page (spec §8.2).
 *
 * The storefront registers `simple`, `variable`, `grouped`, `bundled`, and
 * `digital`. A satellite that adds a product type registers its own form
 * from its service provider's `boot()`:
 *
 * ```php
 * use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
 *
 * if ( class_exists( ProductFormRegistry::class ) ) {
 *     app( ProductFormRegistry::class )->register( 'subscription', 'subscriptions-storefront-purchase-form' );
 * }
 * ```
 *
 * The component is mounted with one prop, `product` (the `Product`), and
 * should extend `Livewire\Product\Forms\PurchaseForm`, which gives it the
 * quantity, rate-limited add to cart, error handling, and the
 * `ecommerce-cart-updated` / `ecommerce-cart-open` events.
 *
 * A type with no registered form whose engine product type implements
 * `ProvidesStorefrontOptions` gets the generic form, which renders the
 * type's option schema. A type with neither shows "This product can't be
 * purchased online".
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductFormRegistry
{
    /**
     * The generic form for types that describe their options.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const OPTIONS_FORM = 'artisanpack-ecommerce-storefront-product-form-options';

    /**
     * Livewire component names, keyed by product type.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected array $forms = [];

    /**
     * Registers (or replaces) the form for a product type.
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey    The engine product type key (`subscription`).
     * @param  string  $component  The Livewire component name.
     *
     * @throws InvalidArgumentException For an empty type key or component name.
     *
     * @return void
     */
    public function register( string $typeKey, string $component ): void
    {
        if ( '' === trim( $typeKey ) || '' === trim( $component ) ) {
            throw new InvalidArgumentException( 'A product form needs a product type key and a Livewire component name.' );
        }

        $this->forms[ $typeKey ] = $component;
    }

    /**
     * Whether a product type has a registered form.
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey  Product type key.
     *
     * @return bool
     */
    public function has( string $typeKey ): bool
    {
        return isset( $this->forms[ $typeKey ] );
    }

    /**
     * Every registered form, keyed by product type.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->forms;
    }

    /**
     * The form for a product: its type's registered form, else the generic
     * options form when the type describes its options, else null (the
     * product can't be bought online).
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product.
     *
     * @return string|null
     */
    public function for( Product $product ): ?string
    {
        if ( $product->typeIsMissing() ) {
            return null;
        }

        return $this->forms[ (string) $product->type ]
            ?? ( $product->productType() instanceof ProvidesStorefrontOptions ? self::OPTIONS_FORM : null );
    }
}
