{{--
    Recently viewed products. See Product\RecentlyViewed.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-recently-viewed>
    @if ( $products->isNotEmpty() )
        <section class="flex flex-col gap-4" aria-labelledby="{{ $headingId }}">
            <h2 id="{{ $headingId }}" class="text-2xl font-bold">{{ $title }}</h2>

            <ul role="list" class="grid gap-4 {{ $gridClass }}">
                @foreach ( $products as $viewed )
                    <li wire:key="recently-viewed-{{ $viewed->id }}">
                        <x-artisanpack-ec-sf-product-card :product="$viewed" :currency="$currency" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
