{{--
    Skeleton placeholder (#110). See Skeleton.

    Design-system Skeleton shapes laid out like the content they stand in
    for, so nothing jumps when it arrives:

    - `text`: `count` text lines, the last one shorter.
    - `card`: `count` product-card skeletons (a Card with a square image,
      three text lines and a pill for the button) in the catalog's grid:
      two columns, three from 768px, four from 1024px.
    - `image`: one square block.

    The shapes sit in an aria-busy region and are hidden from assistive
    tech; one status line beside it names what is loading. The design
    system's shimmer runs in the reading direction and stops under reduced
    motion.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'ec-skeleton' ] ) }} data-skeleton="{{ $variant }}">
    <span class="apui-sr-only" role="status">{{ $label }}</span>

    <div class="ec-skeleton__shapes" data-variant="{{ $variant }}" aria-busy="true" aria-hidden="true">
        @if ( 'image' === $variant )
            <x-artisanpack-skeleton class="ec-skeleton__image" />
        @else
            @for ( $i = 0; $i < $count; $i++ )
                @if ( 'card' === $variant )
                    <x-artisanpack-card class="ec-skeleton-card">
                        <x-slot:media>
                            <x-artisanpack-skeleton class="ec-skeleton-card__media" />
                        </x-slot:media>

                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="medium" />
                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="long" />
                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="short" />
                        <x-artisanpack-skeleton class="ec-skeleton-card__button" />
                    </x-artisanpack-card>
                @else
                    <x-artisanpack-skeleton shape="text" class="ec-skeleton__line" :data-last="$i === $count - 1" />
                @endif
            @endfor
        @endif
    </div>
</div>
