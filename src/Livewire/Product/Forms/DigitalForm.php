<?php

/**
 * Digital product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use Illuminate\Contracts\View\View;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-digital :product="$product" />`
 *
 * A digital product ships nothing. The form says so and lists what the
 * shopper gets: the files, how many downloads and for how long (the
 * product's `meta.digital` or the engine defaults), and a licence key when
 * the product issues them (`meta.licensing`). When the product offers
 * licence types (`meta.licensing.types`), the shopper picks one; it goes on
 * the cart line as `license_type`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class DigitalForm extends PurchaseForm
{
    /**
     * The chosen licence type.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $licenseType = '';

    /**
     * Picks the only licence type when there is one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        parent::mount();

        $types = $this->licenseTypes();

        if ( 1 === count( $types ) ) {
            $this->licenseType = (string) array_key_first( $types );
        }
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

        return view( 'ecommerce-storefront::livewire.product.forms.digital', [
            'notes'         => $this->notes(),
            'licenseTypes'  => $this->licenseTypes(),
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }

    /**
     * Requires a licence type when the product offers them.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function readyToAdd(): bool
    {
        $types = $this->licenseTypes();

        if ( [] !== $types && ! array_key_exists( $this->licenseType, $types ) ) {
            $this->addError( 'licenseType', __( 'Choose a licence.' ) );

            return false;
        }

        return true;
    }

    /**
     * The chosen licence type, when the product offers them.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function lineOptions(): array
    {
        return [] === $this->licenseTypes() ? [] : [ 'license_type' => $this->licenseType ];
    }

    /**
     * The licence types the product offers (`meta.licensing.types`, a
     * list of names), name => name.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function licenseTypes(): array
    {
        $types = [];

        // The engine accepts the listed names as they are (DigitalProductType).
        foreach ( (array) ( $this->product->meta['licensing']['types'] ?? [] ) as $type ) {
            if ( is_scalar( $type ) && '' !== (string) $type ) {
                $types[ (string) $type ] = (string) $type;
            }
        }

        return $types;
    }

    /**
     * What the shopper gets, as short notes.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function notes(): array
    {
        $meta  = (array) ( $this->product->meta ?? [] );
        $notes = [ __( 'Delivered digitally — nothing is shipped.' ) ];

        $files = DigitalFile::query()
            ->where( 'product_id', $this->product->id )
            ->whereNull( 'product_variant_id' )
            ->whereNull( 'archived_at' )
            ->pluck( 'label' )
            ->filter( static fn ( mixed $label ): bool => is_string( $label ) && '' !== trim( $label ) )
            ->values()
            ->all();

        if ( [] !== $files ) {
            $notes[] = __( 'Includes: :files', [ 'files' => implode( ', ', $files ) ] );
        }

        $limit = (int) ( $meta['digital']['download_limit'] ?? config( 'artisanpack.ecommerce.digital.download_limit', 5 ) );
        $days  = (int) ( $meta['digital']['download_expiry_days'] ?? config( 'artisanpack.ecommerce.digital.download_expiry_days', 30 ) );

        $notes[] = match ( true ) {
            $limit > 0 && $days > 0 => trans_choice( 'Download up to :count time within :days days of purchase.|Download up to :count times within :days days of purchase.', $limit, [ 'count' => $limit, 'days' => $days ] ),
            $limit > 0              => trans_choice( 'Download up to :count time.|Download up to :count times.', $limit, [ 'count' => $limit ] ),
            $days > 0               => trans_choice( 'Download as often as you like for :count day after purchase.|Download as often as you like for :count days after purchase.', $days, [ 'count' => $days ] ),
            default                 => __( 'Download as often as you like.' ),
        };

        if ( true === ( $meta['licensing']['enabled'] ?? false ) ) {
            $activations = (int) ( $meta['licensing']['activations_limit'] ?? config( 'artisanpack.ecommerce.licenses.activations_limit', 5 ) );

            $notes[] = $activations > 0
                ? trans_choice( 'Includes a licence key for :count activation per copy.|Includes a licence key for :count activations per copy.', $activations, [ 'count' => $activations ] )
                : __( 'Includes a licence key.' );
        }

        return $notes;
    }
}
