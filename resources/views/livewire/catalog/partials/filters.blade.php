{{--
    The catalog filter groups (#130), rendered in the desktop sidebar and
    again in the mobile drawer (`$idPrefix` keeps their ids apart). See
    Catalog\Index.

    The groups stack between dividers:

    - Category, tag and rating: a design-system RadioGroup (written out, so
      each option can carry its depth and its product count). "All" first;
      sub-categories are indented by depth; the count sits at the end of the
      row; options with no products are disabled.
    - Price: the price range (two thumbs and two amount fields).
    - Attributes and custom option groups: the swatches.
    - In stock, on sale and custom toggles: a design-system Toggle, the
      label followed by the count.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-filters" data-catalog-filters="{{ $idPrefix }}">
    @foreach ( $groups as $group )
        @php
            $ecommerceGroupId = $idPrefix . '-' . preg_replace( '/[^A-Za-z0-9_-]/', '-', $group['key'] );
        @endphp

        <div class="ec-filters__group" wire:key="{{ $ecommerceGroupId }}" data-filter-group="{{ $group['key'] }}">
            @switch ( $group['type'] )
                @case( 'category' )
                @case( 'tag' )
                @case( 'rating' )
                    @php
                        $ecommerceModel   = [ 'category' => 'categoryFilter', 'tag' => 'tagFilter', 'rating' => 'minRating' ][ $group['type'] ];
                        $ecommerceOptions = [
                            [ 'value' => 'rating' === $group['type'] ? '0' : '', 'label' => 'rating' === $group['type'] ? __( 'All ratings' ) : __( 'All' ), 'count' => null, 'depth' => 0, 'disabled' => false ],
                            ...$group['options'],
                        ];
                    @endphp
                    <fieldset class="apui-radio-group ec-filters__options" id="{{ $ecommerceGroupId }}" data-orientation="vertical" data-variant="default">
                        <legend data-scope="radio-group" data-part="label" class="ec-filters__legend">{{ $group['label'] }}</legend>

                        <div data-scope="radio-group" data-part="items">
                            @foreach ( $ecommerceOptions as $ecommerceIndex => $option )
                                <label
                                    data-scope="radio-group"
                                    data-part="item"
                                    class="ec-filters__option"
                                    data-depth="{{ min( 4, (int) ( $option['depth'] ?? 0 ) ) }}"
                                    @if ( $option['disabled'] ) data-disabled @endif
                                    wire:key="{{ $ecommerceGroupId }}-{{ $option['value'] }}"
                                >
                                    <span data-scope="radio-group" data-part="box">
                                        <input
                                            type="radio"
                                            data-scope="radio-group"
                                            data-part="control"
                                            id="{{ $ecommerceGroupId }}-{{ $ecommerceIndex }}"
                                            name="{{ $ecommerceGroupId }}"
                                            value="{{ $option['value'] }}"
                                            wire:model.live.debounce.300ms="{{ $ecommerceModel }}"
                                            @disabled( $option['disabled'] )
                                        >
                                        <span data-scope="radio-group" data-part="indicator" aria-hidden="true"></span>
                                    </span>
                                    <span data-scope="radio-group" data-part="item-text">{{ $option['label'] }}</span>
                                    @if ( null !== $option['count'] )
                                        <span data-scope="radio-group" data-part="item-meta" class="ec-filters__count">{{ $option['count'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
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
                        :from="$group['from']"
                        :to="$group['to']"
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
                    <x-artisanpack-toggle
                        :id="$ecommerceGroupId"
                        :label="null === $group['count'] ? $group['label'] : __( ':label (:count)', [ 'label' => $group['label'], 'count' => $group['count'] ] )"
                        size="sm"
                        wire:model.live.debounce.300ms="{{ 'toggle' === $group['type'] ? $group['model'] : 'extraFilters.' . $group['key'] }}"
                        :disabled="$group['disabled']"
                        class="ec-filters__toggle"
                    />
                    @break
            @endswitch
        </div>
    @endforeach
</div>
