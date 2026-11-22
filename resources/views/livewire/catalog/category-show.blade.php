{{--
    A category page (#131). See Catalog\CategoryShow.

    The breadcrumbs, then the category's header card: the "Category"
    eyebrow, the name at display-lg, the description, and the sub-category
    chips ("All {name}" for this page, marked current, then each
    sub-category), with the image on the end half (stacked above the text
    below 768px). Then the catalog listing for the category.

    The card is a design-system Card written out, as a <div> so the page
    keeps one banner landmark (the store header).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-page" data-category-page="{{ $category->id }}">
    @if ( count( $breadcrumbs ) > 1 )
        <x-artisanpack-breadcrumbs :items="$breadcrumbs" :label="__( 'Breadcrumb' )" :navigate="false" />
    @endif

    <div class="apui-card ec-category-hero" data-surface="default" @if ( null !== $image ) data-with-image @endif data-category-header>
        <div class="ec-category-hero__body">
            <p class="ec-category-hero__eyebrow">{{ __( 'Category' ) }}</p>

            @php( $heading = 'h2' === ( $heading ?? null ) ? 'h2' : 'h1' )
            <{{ $heading }} class="ec-category-hero__title">{{ $category->name }}</{{ $heading }}>

            @if ( null !== $description )
                <div class="ec-category-hero__description" data-category-description>{!! $description !!}</div>
            @endif

            @if ( [] !== $subcategories )
                <nav aria-label="{{ __( 'Sub-categories' ) }}" data-subcategories>
                    <ul role="list" class="ec-category-hero__chips">
                        <li>
                            @if ( null !== $url )
                                <a href="{{ $url }}" class="ec-chip" data-variant="select" aria-current="page" data-subcategory-all>{{ __( 'All :category', [ 'category' => $category->name ] ) }}</a>
                            @else
                                <span class="ec-chip" data-variant="select" aria-current="page" data-subcategory-all>{{ __( 'All :category', [ 'category' => $category->name ] ) }}</span>
                            @endif
                        </li>

                        @foreach ( $subcategories as $subcategory )
                            <li wire:key="subcategory-{{ $subcategory['id'] }}">
                                @if ( null !== $subcategory['url'] )
                                    <a href="{{ $subcategory['url'] }}" class="ec-chip" data-variant="select" data-subcategory="{{ $subcategory['id'] }}">{{ $subcategory['name'] }}</a>
                                @else
                                    <span class="ec-chip" data-variant="select" data-subcategory="{{ $subcategory['id'] }}">{{ $subcategory['name'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>

        @if ( null !== $image )
            @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $image, 1200, 800 ) )
            <div class="ec-category-hero__media">
                <img
                    src="{{ $image['url'] }}"
                    @if ( null !== $image['srcset'] ) srcset="{{ $image['srcset'] }}" sizes="(min-width: 1280px) 616px, (min-width: 768px) 50vw, 100vw" @endif
                    width="{{ $ecommerceSize['width'] }}"
                    height="{{ $ecommerceSize['height'] }}"
                    alt="{{ $image['alt'] }}"
                    data-category-image
                >
            </div>
        @endif
    </div>

    <livewire:artisanpack-ecommerce-storefront-catalog :category="(int) $category->id" :key="'category-catalog-' . $category->id" />
</div>
