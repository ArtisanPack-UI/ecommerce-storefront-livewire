<?php

/**
 * Add-to-Cart Button block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;

/**
 * `artisanpack-commerce/add-to-cart`: a product's purchase form (from the
 * `ProductFormRegistry`, so variations, bundles, and options work), with
 * or without the quantity stepper, and with custom button text.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class AddToCartBlock extends ProductBlock
{
    /**
     * The longest button text, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_BUTTON_TEXT = 60;

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'add-to-cart';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Add-to-Cart Button' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'An "Add to cart" button for a product, with its options.' );
    }

    /**
     * Clamps the button text to {@see self::MAX_BUTTON_TEXT} characters.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Attributes.
     *
     * @return array<string, mixed>
     */
    public function validateAttrs( array $attrs ): array
    {
        $clean = parent::validateAttrs( $attrs );

        $clean['buttonText'] = mb_substr( $clean['buttonText'], 0, self::MAX_BUTTON_TEXT );

        return $clean;
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function productAttributes(): array
    {
        return [
            'showQuantity' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show quantity' ) ] ],
            'buttonText'   => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Button text' ), 'help' => __( 'Leave empty for "Add to cart".' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'cart';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'product' ), __( 'buy' ), __( 'button' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  The product.
     * @param  array<string, mixed>  $attrs    Clamped attributes.
     *
     * @return string|null
     */
    protected function productHtml( Product $product, array $attrs ): ?string
    {
        $form = app( ProductFormRegistry::class )->for( $product );

        if ( null === $form ) {
            return view( 'ecommerce-storefront::blocks.unavailable' )->render();
        }

        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => $form,
            'params'    => [
                'product'      => $product,
                'showQuantity' => $attrs['showQuantity'],
                'buttonText'   => '' === $attrs['buttonText'] ? null : $attrs['buttonText'],
            ],
        ] )->render();
    }
}
