<?php

/**
 * Product page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product;

use ArtisanPackUI\Ecommerce\Catalog\ProductViews;
use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\SafeHtml;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-show :product="$product" />`
 *
 * The product page (spec §7.2):
 *
 * - **Gallery** — the featured image, gallery images, and variant images,
 *   with thumbnails, zoom, and a lightbox; switches to a variant's image.
 * - **Summary** — name, rating summary linking to `#reviews`, display
 *   price, short description (through `kses()` in safe mode), stock status, and SKU, all
 *   following the chosen variant.
 * - **Purchase form** — the product type's form from the
 *   `ProductFormRegistry`, or "can't be purchased online".
 * - **Details** — description (through `kses()` in safe mode), an attributes table, and
 *   shipping/returns content from `ap.ecommerceStorefrontLivewire.product.shippingReturns`
 *   (filter: HTML string, product; empty hides it), in an accordion.
 * - **Sections** — Livewire components from `ap.ecommerceStorefrontLivewire.product.sections`
 *   (filter: key => `[ 'component' => name, 'position' => int, 'params' => [] ]`,
 *   product), rendered below the details in position order with `product`
 *   added to their params.
 *
 * Mounting it records a view through the engine (`ap.ecommerce.product.viewed`).
 * A product that isn't storefront-visible, or whose type is missing, is a 404.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    /**
     * The product.
     *
     * @since 1.0.0
     *
     * @var Product
     */
    #[Locked]
    public Product $product;

    /**
     * The variant the purchase form has matched.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $variantId = null;

    /**
     * The open details section.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    public ?string $openSection = 'description';

    /**
     * Checks the product can be shown, picks up a variant from the query
     * string, and records the view.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        abort_if( ! Product::query()->storefrontVisible()->whereKey( $this->product->id )->exists() || $this->product->typeIsMissing(), 404 );

        $variant = request()->query( 'variant' );

        if ( is_numeric( $variant ) ) {
            $this->variantId = $this->ownVariant( (int) $variant )?->id;
        }

        try {
            ProductViews::record( $this->product, Customer::forUser( auth()->user() ) );
        } catch ( Throwable $exception ) {
            // A listener's failure mustn't take the product page down.
            report( $exception );
        }
    }

    /**
     * Follows the purchase form's variant.
     *
     * @since 1.0.0
     *
     * @param  int       $productId  The product the form belongs to.
     * @param  int|null  $variantId  The matched variant, or null.
     *
     * @return void
     */
    #[On( 'ecommerce-product-variant-selected' )]
    public function variantSelected( int $productId, ?int $variantId = null ): void
    {
        if ( $productId !== (int) $this->product->id ) {
            return;
        }

        $variant         = null === $variantId ? null : $this->ownVariant( $variantId );
        $this->variantId = $variant?->id;

        $image = null === $variant?->image_media_id ? null : ProductImages::media( (int) $variant->image_media_id, (string) $this->product->name );

        if ( null !== $image ) {
            $this->dispatch( 'ecommerce-gallery-show', scope: 'product-' . $this->product->id, url: $image['url'] );
        }
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
        $product  = $this->product;
        $variant  = null === $this->variantId ? null : $this->ownVariant( $this->variantId );
        $subject  = $variant ?? $product;
        $currency = app( StorefrontCart::class )->currency();

        return view( 'ecommerce-storefront::livewire.product.show', [
            'images'           => $this->images(),
            'price'            => app( PriceDisplayResolver::class )->for( $subject, $currency ),
            'stock'            => StockStatus::for( $subject ),
            'sku'              => $variant?->sku ?? $product->sku,
            'shortDescription' => SafeHtml::clean( $product->short_description ),
            'description'      => SafeHtml::clean( $product->description ),
            'specifications'   => $this->specifications(),
            'shippingReturns'  => $this->shippingReturns(),
            'form'             => app( ProductFormRegistry::class )->for( $product ),
            'sections'         => $this->sections(),
            'reviewsUrl'       => '#reviews',
        ] );
    }

    /**
     * A variant of this product, with the product relation set.
     *
     * @since 1.0.0
     *
     * @param  int  $variantId  Variant id.
     *
     * @return ProductVariant|null
     */
    protected function ownVariant( int $variantId ): ?ProductVariant
    {
        $variant = ProductVariant::query()->whereKey( $variantId )->where( 'product_id', $this->product->id )->first();

        return $variant?->setRelation( 'product', $this->product );
    }

    /**
     * The gallery: the product's images, then any variant images not
     * already in it.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function images(): array
    {
        $images = ProductImages::gallery( $this->product );
        $urls   = array_column( $images, 'url' );

        $variantMedia = $this->product->variants()->whereNotNull( 'image_media_id' )->orderBy( 'position' )->orderBy( 'id' )->pluck( 'image_media_id' );

        foreach ( $variantMedia as $mediaId ) {
            $image = ProductImages::media( (int) $mediaId, (string) $this->product->name );

            if ( null !== $image && ! in_array( $image['url'], $urls, true ) ) {
                $images[] = $image + [ 'key' => 'media-' . $mediaId, 'media_id' => (int) $mediaId ];
                $urls[]   = $image['url'];
            }
        }

        return $images;
    }

    /**
     * The attributes table: each attribute's label and its values.
     *
     * @since 1.0.0
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function specifications(): array
    {
        return $this->product->productAttributes()
            ->with( [ 'values' => static fn ( $query ) => $query->orderBy( 'position' )->orderBy( 'id' ) ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( ProductAttribute $attribute ): array => [
                'label' => (string) $attribute->label,
                'value' => $attribute->values->map( static fn ( $value ): string => '' !== trim( (string) $value->label ) ? (string) $value->label : (string) $value->value )->implode( ', ' ),
            ] )
            ->filter( static fn ( array $row ): bool => '' !== $row['label'] && '' !== $row['value'] )
            ->values()
            ->all();
    }

    /**
     * Shipping and returns content from the filter, made safe.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function shippingReturns(): ?string
    {
        $content = applyFilters( 'ap.ecommerceStorefrontLivewire.product.shippingReturns', '', $this->product );

        return is_string( $content ) ? SafeHtml::clean( $content ) : null;
    }

    /**
     * Extra page sections from the filter, in position order.
     *
     * @since 1.0.0
     *
     * @return array<string, array{component: string, params: array<string, mixed>}>
     */
    protected function sections(): array
    {
        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.product.sections', [], $this->product );
        $sections = [];

        foreach ( is_array( $filtered ) ? $filtered : [] as $key => $section ) {
            if ( ! is_string( $key ) || ! is_array( $section ) || ! is_string( $section['component'] ?? null ) || '' === $section['component'] ) {
                continue;
            }

            $sections[ $key ] = [
                'component' => $section['component'],
                'position'  => is_int( $section['position'] ?? null ) ? $section['position'] : 100,
                'params'    => [ ...( is_array( $section['params'] ?? null ) ? $section['params'] : [] ), 'product' => $this->product ],
            ];
        }

        uasort( $sections, static fn ( array $a, array $b ): int => $a['position'] <=> $b['position'] );

        return array_map( static fn ( array $section ): array => [ 'component' => $section['component'], 'params' => $section['params'] ], $sections );
    }
}
