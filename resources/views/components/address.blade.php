{{--
    Address (#81). See Address.

    A plain <address>: the name, the street lines, the city, region and
    postcode, and the country, then the phone muted, on a 22px line. With a
    `title` it sits in a design-system Card under that heading, with an Edit
    link (a link-variant Button) in the header when `edit-href` is given.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceInCard = null !== $title && '' !== $title )
@php( $ecommerceRootAttributes = $ecommerceInCard ? new \Illuminate\View\ComponentAttributeBag() : $attributes )
@if ( $ecommerceInCard )
    <x-artisanpack-card {{ $attributes->class( [ 'ec-address-card' ] ) }} :title="$title" data-address-card>
        @if ( null !== $editHref && '' !== $editHref )
            <x-slot:actions>
                <x-artisanpack-button variant="link" color="primary" size="sm" :href="$editHref" data-address-edit>{{ __( 'Edit' ) }}<span class="apui-sr-only">: {{ $title }}</span></x-artisanpack-button>
            </x-slot:actions>
        @endif

        @include( 'ecommerce-storefront::components.partials.address-lines', [ 'ecommerceAttributes' => $ecommerceRootAttributes, 'lines' => $lines, 'phone' => $phone ] )
    </x-artisanpack-card>
@else
    @include( 'ecommerce-storefront::components.partials.address-lines', [ 'ecommerceAttributes' => $ecommerceRootAttributes, 'lines' => $lines, 'phone' => $phone ] )
@endif
