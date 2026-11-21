<?php

/**
 * Variable product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Catalog\VariantResolver;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-variable :product="$product" />`
 *
 * The variation picker (spec §7.2): one swatch or pill group per variation
 * attribute, driven by the engine's `VariantResolver::matrix()`.
 *
 * - Groups depend on the groups before them: an option that no available
 *   variant offers with the earlier choices is disabled, with the reason
 *   ("Out of stock" or "Not available with your other choices") read to
 *   screen readers.
 * - Choosing an option that rules out a later choice clears that choice and
 *   says so in a live region.
 * - Once every group has a choice, the matched variant goes in the query
 *   string (`?variant=`) so the link reopens it, and
 *   `ecommerce-product-variant-selected` (`productId`, `variantId`) tells
 *   the product page to show its price, stock, SKU, and image.
 * - "Add to cart" can be pressed before every choice is made (#100): it
 *   then shows a field error on the first group still missing a choice
 *   ("Choose a size to add this to your cart.") and moves focus there. It
 *   is disabled only when nothing can be bought: no variants, a
 *   combination that doesn't exist, or a sold-out variant.
 * - A group with more values than `product.variation_select_after` is a
 *   Select instead of swatches.
 *
 * Native radios: arrow keys move within a group, Tab moves between groups.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class VariableForm extends PurchaseForm
{
    /**
     * Languages that capitalize every noun, so an attribute's name keeps
     * its capital inside a sentence ({@see self::inSentence()}).
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const CAPITALIZED_NOUN_LANGUAGES = [ 'de', 'lb' ];

    /**
     * The matched variant's id.
     *
     * Untyped because it comes straight from `?variant=`, which can hold
     * anything (`abc`, `[]`); {@see self::normalizedVariant()} turns it into
     * a positive id or null in `mount()` and `updatedVariant()`.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'variant', history: true )]
    public mixed $variant = null;

    /**
     * The chosen value id per attribute id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, int|string|null>
     */
    public array $selected = [];

    /**
     * The live-region message when a choice was cleared.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $selectionNotice = '';

    /**
     * The variation attributes, for this request.
     *
     * @since 1.0.0
     *
     * @var Collection<int, ProductAttribute>|null
     */
    protected ?Collection $groupCache = null;

    /**
     * The variant matrix, for this request.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $matrixCache = null;

    /**
     * Preselects the variant from the query string, and any group with a
     * single option.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        parent::mount();

        $this->variant = self::normalizedVariant( $this->variant );

        $this->selectVariant( $this->variant );

        foreach ( $this->groups() as $group ) {
            if ( ! isset( $this->selected[ (int) $group->id ] ) && 1 === $group->values->count() ) {
                $this->selected[ (int) $group->id ] = (int) $group->values->first()->id;
            }
        }

        $this->resolveVariant( false );
    }

    /**
     * Follows a variant set from outside the picker (browser history
     * restoring `?variant=`, or `$wire.set()`), so the radios, the page,
     * and the line added to the cart agree.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedVariant(): void
    {
        $this->selectionNotice = '';
        $this->clearChoiceErrors();
        $this->variant = self::normalizedVariant( $this->variant );
        $this->selectVariant( $this->variant );
        $this->resolveVariant();
    }

    /**
     * Chooses `$valueId` for the variation attribute `$attributeId`: what
     * a swatch change calls.
     *
     * The swatches call this action rather than binding `selected` with
     * `wire:model.live`. On Livewire 3 a response that arrives after the
     * shopper has already moved on (arrowing from Red to Blue while Red is
     * still saving) overwrote the pending model change, so Blue was lost.
     * Actions are queued and always run, in order.
     *
     * @since 1.0.0
     *
     * @param  int|string  $attributeId  The attribute id.
     * @param  mixed       $valueId      The chosen value id.
     *
     * @return void
     */
    public function choose( int|string $attributeId, mixed $valueId ): void
    {
        $this->selected[ (int) $attributeId ] = $valueId;

        $this->updatedSelected( $valueId, (int) $attributeId );
    }

    /**
     * Keeps a new choice, clears the choices it rules out, and resolves the
     * variant.
     *
     * Livewire passes no key when it replaces the whole selection (it can
     * send `selected` at once instead of `selected.{id}`, for example while
     * the selection is still empty); the selection is then cleaned and
     * resolved as it stands.
     *
     * @since 1.0.0
     *
     * @param  mixed            $value  The chosen value id, or the whole selection.
     * @param  int|string|null  $key    The attribute id; null for the whole selection.
     *
     * @return void
     */
    public function updatedSelected( mixed $value, int|string|null $key = null ): void
    {
        $this->selectionNotice = '';
        $this->clearChoiceErrors();

        if ( null === $key ) {
            $this->selected = is_array( $value ) ? $value : [];
            $this->selected = $this->cleanSelection();
            $this->resolveVariant();

            return;
        }

        $attributeId = (int) $key;
        $group       = $this->groups()->firstWhere( 'id', $attributeId );
        $choice      = $group?->values->firstWhere( 'id', (int) $value );

        if ( null === $group || null === $choice ) {
            unset( $this->selected[ $key ] );
            $this->selected = $this->cleanSelection();
            $this->resolveVariant();

            return;
        }

        $this->selected                 = $this->cleanSelection();
        $this->selected[ $attributeId ] = (int) $choice->id;

        // Later groups depend on earlier ones: drop any later choice the
        // choices before it no longer allow.
        $kept    = [];
        $cleared = [];
        $after   = false;

        foreach ( $this->groups() as $other ) {
            $otherId = (int) $other->id;

            if ( ! isset( $this->selected[ $otherId ] ) ) {
                $after = $after || $otherId === $attributeId;

                continue;
            }

            if ( $after && ! $this->offered( [ ...$kept, $otherId => $this->selected[ $otherId ] ], true ) ) {
                $cleared[] = (string) $other->label;
                unset( $this->selected[ $otherId ] );

                continue;
            }

            $kept[ $otherId ] = $this->selected[ $otherId ];
            $after            = $after || $otherId === $attributeId;
        }

        if ( [] !== $cleared ) {
            $this->selectionNotice = __( ':choices cleared: not available with :value.', [
                'choices' => implode( ', ', $cleared ),
                'value'   => $this->valueLabel( $choice ),
            ] );
        }

        $this->resolveVariant();
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $blockedReason = $this->blockedReason();

        return view( 'ecommerce-storefront::livewire.product.forms.variable', [
            'groups'        => $this->groups()->map( fn ( ProductAttribute $group ): array => $this->groupData( $group ) )->all(),
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }

    /**
     * Why "Add to cart" is disabled: no variants, a combination that doesn't
     * exist, or the matched variant can't be bought. A choice still to make
     * doesn't disable it; {@see self::readyToAdd()} asks for it.
     *
     * @since 1.0.0
     * @since 2.0.0 A missing choice no longer disables "Add to cart".
     *
     * @return string|null
     */
    public function blockedReason(): ?string
    {
        $groups = $this->groups();

        if ( $groups->isEmpty() || [] === $this->matrix() ) {
            return __( 'This product has no options to buy right now.' );
        }

        if ( null !== $this->missingGroup() ) {
            return null;
        }

        $variantId = self::normalizedVariant( $this->variant );

        if ( null === $variantId ) {
            return __( 'That combination isn\'t available. Choose different options.' );
        }

        $row = $this->rowFor( $variantId );

        return null === $row || ! $row['available'] ? __( 'This option is out of stock.' ) : null;
    }

    /**
     * Requires a matched variant before adding: a missing choice is a field
     * error on its group, anything else a form error.
     *
     * @since 1.0.0
     * @since 2.0.0 A missing choice is an error on its group (`selected.{id}`).
     *
     * @return bool
     */
    protected function readyToAdd(): bool
    {
        // The variant added must be the one the radios show.
        $this->resolveVariant( false );

        $missing = $this->missingGroup();

        if ( null !== $missing ) {
            $this->addError( 'selected.' . $missing->id, $this->missingChoiceMessage( $missing ) );

            // Takes the shopper to the group to fix (WCAG 3.3.1): its first
            // enabled option, or, when every option is disabled (all sold
            // out), the group itself, which then reads its legend and error.
            $this->js( '( ( group ) => { if ( ! group ) { return; } const option = group.querySelector( "input:not(:disabled), select" ); if ( option ) { option.focus(); return; } group.setAttribute( "tabindex", "-1" ); group.focus(); } )( $wire.$el.querySelector( ' . json_encode( '[data-variation-group="' . (int) $missing->id . '"]' ) . ' ) )' );

            return false;
        }

        $reason = $this->blockedReason();

        if ( null !== $reason ) {
            $this->addError( 'variant', $reason );

            return false;
        }

        return true;
    }

    /**
     * The first variation attribute without a choice.
     *
     * @since 2.0.0
     *
     * @return ProductAttribute|null
     */
    protected function missingGroup(): ?ProductAttribute
    {
        return $this->groups()->first( fn ( ProductAttribute $group ): bool => ! isset( $this->selected[ (int) $group->id ] ) );
    }

    /**
     * The field error for a missing choice ("Choose a size to add this to
     * your cart.").
     *
     * @since 2.0.0
     *
     * @param  ProductAttribute  $group  The attribute.
     *
     * @return string
     */
    protected function missingChoiceMessage( ProductAttribute $group ): string
    {
        return __( 'Choose a :name to add this to your cart.', [ 'name' => self::inSentence( (string) $group->label ) ] );
    }

    /**
     * An attribute's name inside a sentence: lower-cased ("Size" → "size"),
     * except in languages that capitalize every noun, such as German.
     *
     * @since 2.0.0
     *
     * @param  string  $label  The attribute's name.
     *
     * @return string
     */
    protected static function inSentence( string $label ): string
    {
        $language = strtolower( preg_split( '/[-_]/', (string) app()->getLocale() )[0] ?? '' );

        return in_array( $language, self::CAPITALIZED_NOUN_LANGUAGES, true ) ? $label : mb_strtolower( $label );
    }

    /**
     * Clears the form's variant error and every group's missing-choice
     * error, once the shopper changes a choice.
     *
     * @since 2.0.0
     *
     * @return void
     */
    protected function clearChoiceErrors(): void
    {
        $this->resetErrorBag( [ 'variant', ...$this->groups()->map( static fn ( ProductAttribute $group ): string => 'selected.' . $group->id )->all() ] );
    }

    /**
     * Selects a variant's values (or nothing for an unknown variant).
     *
     * @since 1.0.0
     *
     * @param  int|null  $variantId  Variant id.
     *
     * @return void
     */
    protected function selectVariant( ?int $variantId ): void
    {
        $this->selected = [];
        $row            = null === $variantId ? null : $this->rowFor( $variantId );

        if ( null === $row ) {
            return;
        }

        foreach ( $this->groups() as $group ) {
            foreach ( $group->values as $value ) {
                if ( in_array( (int) $value->id, $row['attribute_value_ids'], true ) ) {
                    $this->selected[ (int) $group->id ] = (int) $value->id;
                }
            }
        }
    }

    /**
     * The matched variant.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function lineVariantId(): ?int
    {
        return self::normalizedVariant( $this->variant );
    }

    /**
     * A variant id from the query string or the client: a positive integer,
     * or null for anything else.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return int|null
     */
    protected static function normalizedVariant( mixed $value ): ?int
    {
        $variantId = is_int( $value ) || is_string( $value ) ? filter_var( $value, FILTER_VALIDATE_INT ) : false;

        return is_int( $variantId ) && $variantId > 0 ? $variantId : null;
    }

    /**
     * The variation attributes with their values, in position order.
     *
     * @since 1.0.0
     *
     * @return Collection<int, ProductAttribute>
     */
    protected function groups(): Collection
    {
        return $this->groupCache ??= $this->product->productAttributes()
            ->where( 'is_variation', true )
            ->with( [ 'values' => static fn ( $query ) => $query->orderBy( 'position' )->orderBy( 'id' ) ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->filter( static fn ( ProductAttribute $group ): bool => $group->values->isNotEmpty() )
            ->values();
    }

    /**
     * The engine's variant matrix in the shopper's currency.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function matrix(): array
    {
        return $this->matrixCache ??= app( VariantResolver::class )->matrix( $this->product, app( StorefrontCart::class )->currency() );
    }

    /**
     * A variant's matrix row.
     *
     * @since 1.0.0
     *
     * @param  int  $variantId  Variant id.
     *
     * @return array<string, mixed>|null
     */
    protected function rowFor( int $variantId ): ?array
    {
        foreach ( $this->matrix() as $row ) {
            if ( $variantId === $row['variant_id'] ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Whether some variant (an available one, with `$available`) has every
     * value in `$choices`.
     *
     * @since 1.0.0
     *
     * @param  array<int|string, int|string|null>  $choices    Value ids per attribute.
     * @param  bool                                $available  Only count variants that can be bought.
     *
     * @return bool
     */
    protected function offered( array $choices, bool $available ): bool
    {
        $wanted = array_map( 'intval', array_values( array_filter( $choices, static fn ( mixed $value ): bool => null !== $value && '' !== $value ) ) );

        foreach ( $this->matrix() as $row ) {
            if ( ( ! $available || $row['available'] ) && [] === array_diff( $wanted, $row['attribute_value_ids'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The selection with only real attributes and their own values.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function cleanSelection(): array
    {
        $clean = [];

        foreach ( $this->groups() as $group ) {
            $value = $this->selected[ (int) $group->id ] ?? $this->selected[ (string) $group->id ] ?? null;

            if ( null !== $value && '' !== $value && null !== $group->values->firstWhere( 'id', (int) $value ) ) {
                $clean[ (int) $group->id ] = (int) $value;
            }
        }

        return $clean;
    }

    /**
     * Matches the selection to a variant and tells the product page.
     *
     * @since 1.0.0
     *
     * @param  bool  $announce  Dispatch the selection event.
     *
     * @return void
     */
    protected function resolveVariant( bool $announce = true ): void
    {
        $this->selected = $this->cleanSelection();
        $this->variant  = null;

        if ( count( $this->selected ) === $this->groups()->count() && [] !== $this->selected ) {
            $wanted = array_values( $this->selected );
            sort( $wanted );

            foreach ( $this->matrix() as $row ) {
                if ( $wanted === $row['attribute_value_ids'] ) {
                    $this->variant = $row['variant_id'];

                    break;
                }
            }
        }

        if ( $announce ) {
            $this->dispatch( 'ecommerce-product-variant-selected', productId: (int) $this->product->id, variantId: $this->variant );
        }
    }

    /**
     * One group's view data: its options, each with whether it is chosen,
     * disabled, and why.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `control` (swatches or select) and `placeholder`.
     *
     * @param  ProductAttribute  $group  The attribute.
     *
     * @return array{id: int, label: string, options: array<int, array<string, mixed>>, control: string, placeholder: string}
     */
    protected function groupData( ProductAttribute $group ): array
    {
        $before = [];

        foreach ( $this->groups() as $earlier ) {
            if ( (int) $earlier->id === (int) $group->id ) {
                break;
            }

            if ( isset( $this->selected[ (int) $earlier->id ] ) ) {
                $before[ (int) $earlier->id ] = $this->selected[ (int) $earlier->id ];
            }
        }

        $options = [];

        foreach ( $group->values as $value ) {
            $choice    = [ ...$before, (int) $group->id => (int) $value->id ];
            $available = $this->offered( $choice, true );
            $reason    = null;

            if ( ! $available ) {
                $reason = $this->offered( $choice, false ) ? __( 'Out of stock' ) : __( 'Not available with your other choices' );
            }

            $options[] = [
                'value'    => (string) $value->id,
                'label'    => $this->valueLabel( $value ),
                'swatch'   => $value->swatch,
                'selected' => ( $this->selected[ (int) $group->id ] ?? null ) === (int) $value->id,
                'disabled' => ! $available,
                'reason'   => $reason,
            ];
        }

        $selectAfter = (int) config( 'artisanpack.ecommerce-storefront-livewire.product.variation_select_after', 8 );

        return [
            'id'          => (int) $group->id,
            'label'       => (string) $group->label,
            'options'     => $options,
            'control'     => $selectAfter > 0 && count( $options ) > $selectAfter ? 'select' : 'swatches',
            'placeholder' => __( 'Select a :name', [ 'name' => self::inSentence( (string) $group->label ) ] ),
        ];
    }

    /**
     * A value's label (its value when it has none).
     *
     * @since 1.0.0
     *
     * @param  ProductAttributeValue  $value  Value.
     *
     * @return string
     */
    protected function valueLabel( ProductAttributeValue $value ): string
    {
        return '' !== trim( (string) $value->label ) ? (string) $value->label : (string) $value->value;
    }
}
