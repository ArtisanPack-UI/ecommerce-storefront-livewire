{{--
    The catalog filter groups, rendered in the desktop sidebar and again in
    the mobile drawer (`$idPrefix` keeps their ids apart). See Catalog\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-catalog-filters="{{ $idPrefix }}">
    @foreach ( $groups as $group )
        @php( $ecommerceGroupId = $idPrefix . '-' . preg_replace( '/[^A-Za-z0-9_-]/', '-', $group['key'] ) )

        <div wire:key="{{ $ecommerceGroupId }}" data-filter-group="{{ $group['key'] }}">
            @switch ( $group['type'] )
                @case( 'category' )
                @case( 'tag' )
                @case( 'rating' )
                    @php( $ecommerceModel = [ 'category' => 'categoryFilter', 'tag' => 'tagFilter', 'rating' => 'minRating' ][ $group['type'] ] )
                    <fieldset class="flex flex-col gap-1">
                        <legend class="mb-2 text-sm font-semibold">{{ $group['label'] }}</legend>

                        <label class="flex min-h-6 cursor-pointer items-center gap-2 text-sm">
                            <input type="radio" class="radio radio-sm" name="{{ $ecommerceGroupId }}" value="{{ 'rating' === $group['type'] ? 0 : '' }}" wire:model.live.debounce.300ms="{{ $ecommerceModel }}">
                            <span>{{ __( 'All' ) }}</span>
                        </label>

                        @foreach ( $group['options'] as $option )
                            <label
                                @class( [ 'flex min-h-6 items-center gap-2 text-sm', 'cursor-pointer' => ! $option['disabled'], 'cursor-not-allowed opacity-50' => $option['disabled'] ] )
                                style="padding-inline-start: {{ ( $option['depth'] ?? 0 ) * 1 }}rem"
                                wire:key="{{ $ecommerceGroupId }}-{{ $option['value'] }}"
                            >
                                <input type="radio" class="radio radio-sm" name="{{ $ecommerceGroupId }}" value="{{ $option['value'] }}" wire:model.live.debounce.300ms="{{ $ecommerceModel }}" @disabled( $option['disabled'] )>
                                <span>{{ $option['label'] }}</span>
                                <span class="text-xs text-base-content/70">({{ $option['count'] }})</span>
                            </label>
                        @endforeach
                    </fieldset>
                    @break

                @case( 'price' )
                    <x-artisanpack-ec-price-range
                        :label="$group['label']"
                        :min="$group['min']"
                        :max="$group['max']"
                        :step="$group['step']"
                        :currency="$group['currency']"
                        from-model="priceMin"
                        to-model="priceMax"
                        :id-prefix="$ecommerceGroupId"
                    />
                    @break

                @case( 'attribute' )
                @case( 'custom-options' )
                    <x-artisanpack-ec-swatches
                        :name="$ecommerceGroupId"
                        :legend="$group['label']"
                        :options="$group['options']"
                        multiple
                        :id-prefix="$ecommerceGroupId"
                        wire:model.live.debounce.300ms="{{ 'attribute' === $group['type'] ? 'attributeFilters.' . $group['attribute'] : 'extraFilters.' . $group['key'] }}"
                    />
                    @break

                @case( 'toggle' )
                @case( 'custom-toggle' )
                    <x-artisanpack-checkbox
                        :id="$ecommerceGroupId"
                        :label="null === $group['count'] ? $group['label'] : $group['label'] . ' (' . $group['count'] . ')'"
                        wire:model.live.debounce.300ms="{{ 'toggle' === $group['type'] ? $group['model'] : 'extraFilters.' . $group['key'] }}"
                        :disabled="$group['disabled']"
                        class="checkbox-sm"
                    />
                    @break
            @endswitch
        </div>
    @endforeach
</div>
