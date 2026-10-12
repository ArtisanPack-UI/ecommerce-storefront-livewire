{{--
    A category page. See Catalog\CategoryShow.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-category-page="{{ $category->id }}">
    @if ( count( $breadcrumbs ) > 1 )
        <nav aria-label="{{ __( 'Breadcrumb' ) }}">
            <x-artisanpack-breadcrumbs :items="$breadcrumbs" no-wire-navigate />
        </nav>
    @endif

    <header class="flex flex-col gap-4 sm:flex-row sm:items-start">
        @if ( null !== $image )
            @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $image, 384, 384 ) )
            <img
                src="{{ $image['url'] }}"
                @if ( null !== $image['srcset'] ) srcset="{{ $image['srcset'] }}" sizes="(min-width: 640px) 12rem, 100vw" @endif
                width="{{ $ecommerceSize['width'] }}"
                height="{{ $ecommerceSize['height'] }}"
                alt="{{ $image['alt'] }}"
                class="aspect-square w-full rounded-box object-cover sm:w-48"
                data-category-image
            >
        @endif

        <div class="flex flex-col gap-3">
            <h1 class="text-3xl font-bold">{{ $category->name }}</h1>

            @if ( null !== $description )
                <div class="prose max-w-none" data-category-description>{!! $description !!}</div>
            @endif
        </div>
    </header>

    @if ( [] !== $subcategories )
        <nav aria-label="{{ __( 'Sub-categories' ) }}" data-subcategories>
            <ul role="list" class="flex flex-wrap gap-2">
                @foreach ( $subcategories as $subcategory )
                    <li wire:key="subcategory-{{ $subcategory['id'] }}">
                        @if ( null !== $subcategory['url'] )
                            <a href="{{ $subcategory['url'] }}" class="btn btn-sm btn-outline rounded-full">{{ $subcategory['name'] }}</a>
                        @else
                            <span class="badge badge-outline">{{ $subcategory['name'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <livewire:artisanpack-ecommerce-storefront-catalog :category="(int) $category->id" :key="'category-catalog-' . $category->id" />
</div>
