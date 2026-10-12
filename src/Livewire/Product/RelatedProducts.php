<?php

/**
 * Related products component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product;

use ArtisanPackUI\Ecommerce\Catalog\RelatedProducts as EngineRelatedProducts;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-related-products :product="$product" type="upsell" />`
 *
 * A row of product cards from the engine's `RelatedProducts` (spec §7.2,
 * §7.3, S13):
 *
 * - with a `product`, its `related` products ("Related products", the
 *   default), `upsell`s ("You may also like"), or `cross_sell`s;
 * - without one and with `type="cross_sell"`, cross-sells for the
 *   shopper's cart ("Complete your order"), refreshed when the cart
 *   changes.
 *
 * The section loads after the page (`#[Lazy]`), showing skeleton cards
 * until then, and renders nothing when there is nothing to suggest. On
 * small screens the cards scroll sideways with "Previous" and "Next"
 * buttons; from `md` up they are a grid. Simple products get quick add.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
#[Lazy]
class RelatedProducts extends Component
{
    use AddsToCart;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The product the suggestions are for; null for the cart's cross-sells.
     *
     * @since 1.0.0
     *
     * @var Product|null
     */
    #[Locked]
    public ?Product $product = null;

    /**
     * The relation type: `related`, `upsell`, or `cross_sell`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $type = ProductRelation::RELATED;

    /**
     * The most products to show (1–12); defaults to `related.limit`.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $limit = null;

    /**
     * The section heading; defaults to one for the type.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $heading = null;

    /**
     * Grid columns from `lg` up (1–6).
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $columns = 4;

    /**
     * Normalizes the type and limit.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->type  = in_array( $this->type, ProductRelation::TYPES, true ) ? $this->type : ProductRelation::RELATED;
        $this->limit = max( 1, min( 12, $this->limit ?? (int) config( 'artisanpack.ecommerce-storefront-livewire.related.limit', 4 ) ) );
    }

    /**
     * The cart's cross-sells follow the cart; product suggestions don't
     * need to.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return $this->forCart() ? [ 'ecommerce-cart-updated' => '$refresh' ] : [];
    }

    /**
     * Skeleton cards while the section loads. Runs before mount(), so the
     * props come from `$params`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $params  The props the section was given.
     *
     * @return View
     */
    public function placeholder( array $params = [] ): View
    {
        $this->product = ( $params['product'] ?? null ) instanceof Product ? $params['product'] : null;
        $this->type    = is_string( $params['type'] ?? null ) ? $params['type'] : ProductRelation::RELATED;
        $this->heading = is_string( $params['heading'] ?? null ) ? $params['heading'] : null;
        $this->limit   = is_int( $params['limit'] ?? null ) ? $params['limit'] : null;

        $this->mount();

        return view( 'ecommerce-storefront::livewire.product.related-products-placeholder', [
            'title' => $this->headingText(),
            'count' => min( 4, (int) $this->limit ),
        ] );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::livewire.product.related-products', [
            'products'  => $this->products(),
            'headingId' => 'ec-related-' . $this->type . '-' . ( $this->product?->id ?? 'cart' ),
            'title'     => $this->headingText(),
            'currency'  => app( StorefrontCart::class )->currency(),
            'gridClass' => GridColumns::large( $this->columns ),
        ] );
    }

    /**
     * Whether this section lists the cart's cross-sells.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function forCart(): bool
    {
        return null === $this->product && ProductRelation::CROSS_SELL === $this->type;
    }

    /**
     * The suggestions. A failure in the engine or a listener hides the
     * section rather than the page.
     *
     * @since 1.0.0
     *
     * @return Collection<int, Product>
     */
    protected function products(): Collection
    {
        $related = app( EngineRelatedProducts::class );
        $limit   = (int) $this->limit;

        try {
            if ( null !== $this->product ) {
                return $related->for( $this->product, $this->type, $limit, ProductCard::relations() );
            }

            $cart = $this->forCart() ? $this->cart() : null;

            return null === $cart ? new Collection() : $related->crossSellsForCart( $cart, $limit, ProductCard::relations() );
        } catch ( Throwable $exception ) {
            report( $exception );

            return new Collection();
        }
    }

    /**
     * The heading: the one given, else one for the type.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function headingText(): string
    {
        if ( null !== $this->heading && '' !== trim( $this->heading ) ) {
            return $this->heading;
        }

        return match ( true ) {
            $this->forCart()                              => __( 'Complete your order' ),
            ProductRelation::UPSELL === $this->type       => __( 'You may also like' ),
            ProductRelation::CROSS_SELL === $this->type   => __( 'Frequently bought together' ),
            default                                       => __( 'Related products' ),
        };
    }
}
