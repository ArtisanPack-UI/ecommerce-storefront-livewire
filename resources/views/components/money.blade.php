{{--
    Money (#75). See Money.

    Design-system text styles only (ec-money in resources/css/storefront.css):
    tabular figures that never wrap, each figure isolated left to right. A
    negative amount shows "−" and reads "Minus"; a converted amount shows its
    store-currency equivalent after it, muted, read as "approximately".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( null === $formatted() )
    <span {{ $attributes->class( [ 'ec-money' ] ) }} data-tone="muted" data-money><span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'No amount' ) }}</span></span>
@else
    <span {{ $attributes->class( [ 'ec-money' ] ) }} data-money data-currency="{{ $currency }}"><bdi dir="ltr">@if ( $isNegative() )<span aria-hidden="true">{{ \ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\Money::MINUS }}</span><span class="apui-sr-only">{{ __( 'Minus' ) }} </span>@endif{{ $magnitude() }}</bdi>@if ( null !== ( $ecommerceEquivalent = $formattedEquivalent() ) )<span class="ec-money__equivalent" data-money-equivalent><span aria-hidden="true">&asymp; </span><span class="apui-sr-only">{{ __( 'approximately' ) }} </span><bdi dir="ltr">{{ $ecommerceEquivalent }}</bdi></span>@endif</span>
@endif
