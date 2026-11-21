{{--
    The Checkout Steps block's editor preview (#116): the steps in the
    chosen layout, without a cart. See Blocks\CheckoutStepsBlock.

    The commerce block frame's dashed notice (#91: a soft info badge naming
    the layout and one line of help), then:

    - multi-step: the design-system Steps with the first step current, over
      a Card for that step holding skeletons of a field and a button;
    - single page: one Card per step, a numbered marker beside its title;
      the first one open, the rest on the sunken surface in muted text.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;

    $ecommerceSinglePage = CheckoutLayout::SINGLE_PAGE === $layout;
@endphp
<div class="ec-checkout-preview" data-checkout-preview="{{ $layout }}">
    <div class="ec-block-notice ec-block-notice--sample" data-commerce-sample>
        <x-artisanpack-badge color="info">{{ $ecommerceSinglePage ? __( 'Single page' ) : __( 'Multi-step' ) }}</x-artisanpack-badge>
        <p class="ec-block-notice__message">{{ __( 'Shoppers check out their own cart here.' ) }}</p>
    </div>

    @if ( $ecommerceSinglePage )
        <ol role="list" class="ec-checkout-preview__sections" aria-label="{{ __( 'Checkout steps' ) }}">
            @foreach ( $steps as $index => $step )
                <li data-checkout-preview-step="{{ $step['key'] }}" @if ( 0 === $index ) aria-current="step" @endif>
                    <x-artisanpack-card :surface="0 === $index ? 'default' : 'sunken'" class="ec-checkout-preview__section" :data-upcoming="0 !== $index">
                        <p class="ec-checkout-preview__section-title">
                            <span class="ec-checkout-preview__marker" aria-hidden="true">{{ $index + 1 }}</span>
                            <span>{{ $step['label'] }}</span>
                        </p>
                    </x-artisanpack-card>
                </li>
            @endforeach
        </ol>
    @else
        <x-artisanpack-steps :value="1" :label="__( 'Checkout steps' )" class="ec-checkout-preview__steps">
            @foreach ( $steps as $step )
                <x-artisanpack-step :title="$step['label']" data-checkout-preview-step="{{ $step['key'] }}" />
            @endforeach
        </x-artisanpack-steps>

        @if ( [] !== $steps )
            <x-artisanpack-card class="ec-checkout-preview__current" :title="$steps[0]['label']">
                <div class="ec-checkout-preview__placeholder" aria-hidden="true">
                    <x-artisanpack-skeleton class="ec-checkout-preview__field" />
                    <x-artisanpack-skeleton class="ec-checkout-preview__button" />
                </div>
            </x-artisanpack-card>
        @endif
    @endif
</div>
