<?php

/**
 * Cart Contents block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;

/**
 * `artisanpack-commerce/cart-contents`: the shopper's cart — lines,
 * coupon, shipping estimate, totals, and cross-sells (spec §11.3, S37).
 * Renders Cart\Index, with the cross-sells and the coupon field each
 * optional.
 *
 * The editor preview never reads or creates a cart: it shows a sample
 * cart built from the newest products, which isn't saved anywhere.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CartContentsBlock extends StorefrontBlock
{
    /**
     * How many products the preview's sample cart holds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const SAMPLE_LINES = 2;

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'cart-contents';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Cart Contents' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'The shopper\'s cart: items, coupon, shipping estimate, totals, and cross-sells.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'showCrossSells' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show cross-sells' ) ] ],
            'showCoupon'     => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show coupon field' ) ] ],
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
        return [ __( 'cart' ), __( 'basket' ), __( 'coupon' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function emptyMessage(): string
    {
        return __( 'Add a product to the store to preview the cart.' );
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string|null
     */
    protected function html( array $attrs ): ?string
    {
        if ( self::previewing() ) {
            return $this->preview( $attrs );
        }

        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-cart',
            'params'    => [
                'showCrossSells' => $attrs['showCrossSells'],
                'showCoupon'     => $attrs['showCoupon'],
            ],
        ] )->render();
    }

    /**
     * The editor preview: a sample cart of the newest products, never
     * saved, or null when the store has no products.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string|null
     */
    protected function preview( array $attrs ): ?string
    {
        $currency = app( StorefrontCart::class )->currency();
        $prices   = app( PriceDisplayResolver::class );
        $lines    = [];

        $products = Product::query()->storefrontVisible()->with( ProductCard::relations() )->orderByDesc( 'created_at' )->orderByDesc( 'id' )->limit( 10 )->get()
            ->reject( static fn ( Product $product ): bool => $product->typeIsMissing() );

        foreach ( $products as $product ) {
            $price = $prices->for( $product, $currency );

            if ( null === $price ) {
                continue;
            }

            $lines[] = [
                'name'   => (string) $product->name,
                'image'  => ProductImages::card( $product ),
                'amount' => (int) $price->price->getAmount(),
            ];

            if ( count( $lines ) >= self::SAMPLE_LINES ) {
                break;
            }
        }

        if ( [] === $lines ) {
            return null;
        }

        return view( 'ecommerce-storefront::blocks.cart-preview', [
            'lines'          => $lines,
            'subtotal'       => array_sum( array_column( $lines, 'amount' ) ),
            'currency'       => $currency,
            'showCoupon'     => $attrs['showCoupon'],
            'showCrossSells' => $attrs['showCrossSells'],
        ] )->render();
    }
}
