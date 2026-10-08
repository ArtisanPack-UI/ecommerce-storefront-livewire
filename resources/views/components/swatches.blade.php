{{--
    Swatch selector. See Swatches.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceModel = $attributes->whereStartsWith( 'wire:model' ) )
<fieldset {{ $attributes->whereDoesntStartWith( 'wire:model' )->class( [ 'flex flex-col gap-2' ] ) }} data-swatches="{{ $name }}">
    <legend class="mb-2 text-sm font-semibold">{{ $legend }}</legend>

    <div class="flex flex-wrap gap-2">
        @foreach ( $items as $index => $item )
            @php( $ecommerceInputId = $idPrefix . '-' . $index )
            @php( $ecommerceTitle = $item['reason'] ?? ( null !== $item['color'] || null !== $item['image'] ? $item['label'] : null ) )
            <label for="{{ $ecommerceInputId }}" class="relative" wire:key="{{ $ecommerceInputId }}" @if ( null !== $ecommerceTitle ) title="{{ $ecommerceTitle }}" @endif>
                <input
                    id="{{ $ecommerceInputId }}"
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $multiple ? $name . '[]' : $name }}"
                    value="{{ $item['value'] }}"
                    class="peer sr-only"
                    {{ $ecommerceModel }}
                    @checked( $item['selected'] )
                    @disabled( $item['disabled'] )
                    data-swatch-value="{{ $item['value'] }}"
                >
                <span @class( [
                    'inline-flex min-h-8 min-w-8 cursor-pointer items-center gap-2 rounded-full border border-base-content/20 px-3 py-1 text-sm transition',
                    'peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary',
                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary',
                    'peer-disabled:cursor-not-allowed peer-disabled:opacity-40 peer-disabled:line-through',
                    'px-1' => null !== $item['color'] || null !== $item['image'],
                ] )>
                    @if ( null !== $item['color'] )
                        <span class="h-6 w-6 rounded-full border border-base-content/20" style="background-color: {{ $item['color'] }}" aria-hidden="true"></span>
                    @elseif ( null !== $item['image'] )
                        <img src="{{ $item['image'] }}" alt="" class="h-6 w-6 rounded-full object-cover" aria-hidden="true">
                    @endif

                    <span @class( [ 'sr-only' => null !== $item['color'] || null !== $item['image'] ] )>{{ $item['label'] }}</span>

                    @if ( null !== $item['count'] )
                        <span class="text-xs text-base-content/70">({{ $item['count'] }})</span>
                    @endif

                    @if ( null !== $item['reason'] )
                        <span class="sr-only">{{ $item['reason'] }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
