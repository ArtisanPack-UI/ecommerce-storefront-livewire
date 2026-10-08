{{--
    Related products while they load. See Product\RelatedProducts.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
{{-- The root is a <div>, like the component's, so Livewire can swap it in. --}}
<div data-related-placeholder>
    <section class="flex flex-col gap-4">
        <h2 class="text-2xl font-bold">{{ $title }}</h2>

        <x-artisanpack-ec-skeleton variant="card" :count="$count" :label="__( 'Loading suggestions…' )" />
    </section>
</div>
