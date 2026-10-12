<?php

/**
 * Checkout Steps block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutSteps;

/**
 * `artisanpack-commerce/checkout-steps`: the checkout — contact, address,
 * shipping, payment, and review (spec §11.3, S37). Renders
 * Checkout\Index; the "Layout" setting overrides the store's checkout
 * layout for this block.
 *
 * The editor preview never reads or creates a cart: it shows the steps
 * in the chosen layout.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CheckoutStepsBlock extends StorefrontBlock
{
    /**
     * The "Layout" value that follows the store setting.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const STORE_LAYOUT = 'store';

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'checkout-steps';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Checkout Steps' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'The checkout: contact, address, shipping, payment, and review.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'layout' => [
                'type'      => 'string',
                'enum'      => [ self::STORE_LAYOUT, CheckoutLayout::MULTI_STEP, CheckoutLayout::SINGLE_PAGE ],
                'default'   => self::STORE_LAYOUT,
                'apControl' => [ 'control' => 'select', 'label' => __( 'Layout' ), 'options' => self::options( [
                    self::STORE_LAYOUT          => __( 'Store setting' ),
                    CheckoutLayout::MULTI_STEP  => __( 'Multi-step' ),
                    CheckoutLayout::SINGLE_PAGE => __( 'Single page' ),
                ] ) ],
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'money-alt';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'checkout' ), __( 'payment' ) ];
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
        $override = self::STORE_LAYOUT === $attrs['layout'] ? '' : $attrs['layout'];

        if ( self::previewing() ) {
            return view( 'ecommerce-storefront::blocks.checkout-preview', [
                'layout' => CheckoutLayout::resolve( $override ),
                'steps'  => array_values( CheckoutSteps::core( true ) ),
            ] )->render();
        }

        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-checkout',
            'params'    => [ 'layoutOverride' => $override ],
        ] )->render();
    }
}
