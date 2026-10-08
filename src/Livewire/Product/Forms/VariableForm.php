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
 * - "Add to cart" stays disabled, naming the missing choice, until a variant
 *   is matched.
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
     * The matched variant's id.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Url( as: 'variant', history: true )]
    public ?int $variant = null;

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
        $this->resetErrorBag( 'variant' );
        $this->selectVariant( $this->variant );
        $this->resolveVariant();
    }

    /**
     * Keeps a new choice, clears the choices it rules out, and resolves the
     * variant.
     *
     * @since 1.0.0
     *
     * @param  mixed       $value  The chosen value id.
     * @param  int|string  $key    The attribute id.
     *
     * @return void
     */
    public function updatedSelected( mixed $value, int|string $key ): void
    {
        $this->selectionNotice = '';
        $this->resetErrorBag( 'variant' );

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
     * Why "Add to cart" is disabled: a missing choice, no variants, or the
     * matched variant can't be bought.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function blockedReason(): ?string
    {
        $groups = $this->groups();

        if ( $groups->isEmpty() || [] === $this->matrix() ) {
            return __( 'This product has no options to buy right now.' );
        }

        $missing = $groups->first( fn ( ProductAttribute $group ): bool => ! isset( $this->selected[ (int) $group->id ] ) );

        if ( null !== $missing ) {
            return __( 'Choose an option for :name.', [ 'name' => (string) $missing->label ] );
        }

        if ( null === $this->variant ) {
            return __( 'That combination isn\'t available. Choose different options.' );
        }

        $row = $this->rowFor( $this->variant );

        return null === $row || ! $row['available'] ? __( 'This option is out of stock.' ) : null;
    }

    /**
     * Requires a matched variant before adding.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function readyToAdd(): bool
    {
        // The variant added must be the one the radios show.
        $this->resolveVariant( false );

        $reason = $this->blockedReason();

        if ( null !== $reason ) {
            $this->addError( 'variant', $reason );

            return false;
        }

        return true;
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
        return $this->variant;
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
     *
     * @param  ProductAttribute  $group  The attribute.
     *
     * @return array{id: int, label: string, options: array<int, array<string, mixed>>}
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

        return [ 'id' => (int) $group->id, 'label' => (string) $group->label, 'options' => $options ];
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
