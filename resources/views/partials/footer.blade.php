{{--
    The standalone layout's footer (#129). See layouts/app.

    The top row: the store's mark and name (linking to `$homeUrl`) over
    `storefront.tagline`, then the link columns from
    Support\StorefrontNavigation::footer() (Shop, Help, Account): one
    "Footer" nav landmark, each column a list named by its heading (so the
    footer's "Account" never clashes with the account screens' own
    navigation). Below a divider: the copyright, the
    currency switcher (when the store sells in more than one) and the theme
    toggle. Below 768px the columns sit two to a row under the brand.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
@php
    use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontNavigation;

    $ecommerceTagline = config( 'artisanpack.ecommerce-storefront-livewire.storefront.tagline' );
    $ecommerceColumns = StorefrontNavigation::footer();
@endphp
<footer class="ec-footer" data-storefront-footer>
    <div class="ec-footer__inner">
        <div class="ec-footer__top">
            <div class="ec-footer__about">
                <a href="{{ $homeUrl }}" class="ec-brand">
                    <span class="ec-brand__mark" aria-hidden="true">{{ StorefrontNavigation::mark( $storeName ) }}</span>
                    <span class="ec-brand__name">{{ $storeName }}</span>
                </a>

                @if ( is_string( $ecommerceTagline ) && '' !== trim( $ecommerceTagline ) )
                    <p class="ec-footer__tagline" data-storefront-tagline>{{ $ecommerceTagline }}</p>
                @endif
            </div>

            @if ( [] !== $ecommerceColumns )
                <nav class="ec-footer__nav" aria-label="{{ __( 'Footer' ) }}">
                    @foreach ( $ecommerceColumns as $ecommerceKey => $ecommerceColumn )
                        <div class="ec-footer__column" data-footer-column="{{ $ecommerceKey }}">
                            <h2 id="ec-footer-{{ $ecommerceKey }}" class="ec-footer__title">{{ $ecommerceColumn['title'] }}</h2>

                            <ul role="list" class="ec-footer__links" aria-labelledby="ec-footer-{{ $ecommerceKey }}">
                                @foreach ( $ecommerceColumn['links'] as $ecommerceLink )
                                    <li><a href="{{ $ecommerceLink['url'] }}" class="ec-footer__link">{{ $ecommerceLink['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="ec-footer__bottom">
            <p class="ec-footer__copyright">&copy; {{ now()->year }} {{ $storeName }}</p>

            <div class="ec-footer__settings">
                <livewire:artisanpack-ecommerce-storefront-currency-switcher placement="footer" />
                <x-artisanpack-theme-toggle variant="icon" />
            </div>
        </div>
    </div>
</footer>
