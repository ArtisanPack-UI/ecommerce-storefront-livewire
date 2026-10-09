{{--
    Category tiles. See CategoryGrid.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( [] !== $tiles )
    <section {{ $attributes->class( [ 'flex flex-col gap-4' ] ) }} @if ( null !== $heading ) aria-labelledby="{{ $headingId }}" @else aria-label="{{ __( 'Categories' ) }}" @endif data-category-grid>
        @if ( null !== $heading )
            <h2 id="{{ $headingId }}" class="text-2xl font-bold">{{ $heading }}</h2>
        @endif

        <ul role="list" class="grid gap-4 {{ $gridClass }}">
            @foreach ( $tiles as $tile )
                <li data-category-tile="{{ $tile['id'] }}">
                    <x-artisanpack-card class="relative h-full border border-base-content/10 transition-shadow hover:shadow-md focus-within:shadow-md">
                        @if ( $showImages )
                            <x-slot:figure class="aspect-[4/3] bg-base-200">
                                @if ( null !== $tile['image'] )
                                    @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $tile['image'], 800, 600 ) )
                                    <img
                                        src="{{ $tile['image']['url'] }}"
                                        @if ( null !== $tile['image']['srcset'] ) srcset="{{ $tile['image']['srcset'] }}" sizes="(min-width: 1024px) 33vw, 50vw" @endif
                                        width="{{ $ecommerceSize['width'] }}"
                                        height="{{ $ecommerceSize['height'] }}"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                    >
                                @else
                                    <div class="flex h-full w-full items-center justify-center" aria-hidden="true">
                                        <x-artisanpack-icon name="o-squares-2x2" class="h-10 w-10 opacity-30" />
                                    </div>
                                @endif
                            </x-slot:figure>
                        @endif

                        <div class="flex flex-col gap-1">
                            <h3 class="font-semibold">
                                @if ( null !== $tile['url'] )
                                    <a href="{{ $tile['url'] }}" class="link-hover after:absolute after:inset-0 after:content-[''] focus-visible:outline-none">{{ $tile['name'] }}</a>
                                @else
                                    {{ $tile['name'] }}
                                @endif
                            </h3>

                            @if ( $showCounts )
                                <p class="text-sm text-base-content/70" data-category-count>{{ trans_choice( ':count product|:count products', $tile['count'], [ 'count' => $tile['count'] ] ) }}</p>
                            @endif
                        </div>
                    </x-artisanpack-card>
                </li>
            @endforeach
        </ul>
    </section>
@endif
