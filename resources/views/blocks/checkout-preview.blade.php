{{--
    The Checkout Steps block's editor preview: the steps in the chosen
    layout, without a cart. See Blocks\CheckoutStepsBlock.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;
@endphp
<div class="flex flex-col gap-4" data-checkout-preview="{{ $layout }}">
    <div class="flex flex-col items-start gap-1 rounded-box border border-dashed border-base-content/30 p-3 text-sm" data-commerce-sample>
        <span class="badge badge-neutral badge-sm">
            {{ CheckoutLayout::SINGLE_PAGE === $layout ? __( 'Single page' ) : __( 'Multi-step' ) }}
        </span>
        <span>{{ __( 'Shoppers check out their own cart here.' ) }}</span>
    </div>

    @if ( CheckoutLayout::SINGLE_PAGE === $layout )
        @foreach ( $steps as $index => $step )
            <section class="rounded-box border border-base-content/10 p-4 {{ 0 === $index ? '' : 'opacity-60' }}" data-checkout-preview-step="{{ $step['key'] }}">
                <p class="font-semibold">{{ $index + 1 }}. {{ $step['label'] }}</p>
            </section>
        @endforeach
    @else
        <ol class="flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="{{ __( 'Checkout steps' ) }}">
            @foreach ( $steps as $index => $step )
                <li class="{{ 0 === $index ? 'font-semibold' : 'text-base-content/60' }}" data-checkout-preview-step="{{ $step['key'] }}">{{ $index + 1 }}. {{ $step['label'] }}</li>
            @endforeach
        </ol>

        <section class="rounded-box border border-base-content/10 p-4">
            <p class="font-semibold">{{ $steps[0]['label'] ?? '' }}</p>
        </section>
    @endif
</div>
