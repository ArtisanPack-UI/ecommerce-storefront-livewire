{{--
    The lines of an Address (#81): the <address> itself, or "No address".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
@if ( [] === $lines )
    <p {{ $ecommerceAttributes->class( [ 'ec-address', 'ec-address--empty' ] ) }} data-address="empty">{{ __( 'No address' ) }}</p>
@else
    <address {{ $ecommerceAttributes->class( [ 'ec-address' ] ) }} data-address>
        @foreach ( $lines as $line )
            <span class="ec-address__line">{{ $line }}</span>
        @endforeach

        @if ( null !== $phone )
            <span class="ec-address__line ec-address__phone"><span class="apui-sr-only">{{ __( 'Phone:' ) }}</span> {{ $phone }}</span>
        @endif
    </address>
@endif
