{{--
    Empty state (#84). See EmptyState.

    The design-system EmptyState as a status region: the icon tile, the
    title, the description and, from the slot, the actions. With `card` it
    sits in a Card.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $card )
    <x-artisanpack-card class="ec-empty-state-card" data-empty-state-card>
        @include( 'ecommerce-storefront::components.partials.empty-state-body' )
    </x-artisanpack-card>
@else
    @include( 'ecommerce-storefront::components.partials.empty-state-body' )
@endif
