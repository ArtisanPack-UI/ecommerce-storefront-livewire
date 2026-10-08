{{--
    Skeleton placeholder. See Skeleton.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'w-full' ] ) }} aria-busy="true" data-skeleton="{{ $variant }}">
    <span class="sr-only" role="status">{{ $label }}</span>

    <div aria-hidden="true" @class( [
        'flex flex-col gap-2' => 'text' === $variant,
        'grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4' => 'card' === $variant,
    ] )>
        @if ( 'image' === $variant )
            <div class="skeleton aspect-square w-full"></div>
        @else
            @for ( $i = 0; $i < $count; $i++ )
                @if ( 'card' === $variant )
                    <div class="flex flex-col gap-3">
                        <div class="skeleton aspect-square w-full"></div>
                        <div class="skeleton h-4 w-3/4"></div>
                        <div class="skeleton h-4 w-1/3"></div>
                    </div>
                @else
                    <div @class( [ 'skeleton h-4', 'w-2/3' => $i === $count - 1, 'w-full' => $i !== $count - 1 ] )></div>
                @endif
            @endfor
        @endif
    </div>
</div>
