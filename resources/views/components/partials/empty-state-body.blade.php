{{--
    The EmptyState itself, shared by the plain and Card forms of
    <x-artisanpack-ec-empty-state>. `role="status"` only with `live`.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
<x-artisanpack-empty-state
    {{ $attributes->class( [ 'ec-empty-state' ] )->merge( $live ? [ 'role' => 'status' ] : [] ) }}
    data-empty-state
    :icon="$icon"
    :title="$title"
    :description="null !== $description && '' !== $description ? $description : null"
    :size="$size"
    :heading-level="$headingLevel"
>
    @if ( $slot->isNotEmpty() )
        <x-slot:actions>{{ $slot }}</x-slot:actions>
    @endif
</x-artisanpack-empty-state>
