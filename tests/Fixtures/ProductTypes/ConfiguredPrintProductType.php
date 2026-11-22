<?php

declare( strict_types=1 );

namespace Tests\Fixtures\ProductTypes;

use ArtisanPackUI\Ecommerce\Contracts\DescribesLineOptions;
use ArtisanPackUI\Ecommerce\Contracts\ProvidesStorefrontOptions;
use ArtisanPackUI\Ecommerce\Contracts\QuotesPrice;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\ProductTypes\AbstractProductType;
use ArtisanPackUI\Ecommerce\Support\StorefrontOptionSchema;
use Money\Money;

/**
 * A configurable print using the engine 1.4 storefront schema: a swatch
 * frame (one choice disabled, one an image), card sizes (the large one rules
 * out rag paper), a paper radio, a mat select shown only for a frame, and a
 * gift note shown only for a gift. Each choice's `price_delta` (USD) is
 * added to the product's price. Only load it with engine 1.4+.
 */
class ConfiguredPrintProductType extends AbstractProductType implements DescribesLineOptions, ProvidesStorefrontOptions, QuotesPrice
{
    public const KEY = 'configured-print';

    /** @var array<string, int> Price differences in cents, keyed `field:value`. */
    public const DELTAS = [ 'frame:oak' => 1500, 'size:large' => 4500, 'paper:rag' => 1800, 'mat:black' => 800 ];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Configured print';
    }

    public function requiresFulfillment(): bool
    {
        return true;
    }

    public function isInventoryTracked(): bool
    {
        return true;
    }

    public function validateCartOptions( Product $product, array $options ): array
    {
        $fields = $this->storefrontOptions( $product );
        $chosen = app( StorefrontOptionSchema::class )->validate( $fields, $options );

        return array_intersect_key( $chosen, array_flip( [ 'frame', 'size', 'paper', 'mat', 'gift', 'gift_note' ] ) );
    }

    public function priceLine( Product $product, array $options, int $quantity, string $currency ): Money
    {
        $line = parent::priceLine( $product, $options, 1, $currency );

        foreach ( $this->deltas( $options, $currency ) as $delta ) {
            $line = $line->add( $delta['amount'] );
        }

        return $line->multiply( (string) $quantity );
    }

    public function quoteBreakdown( Product $product, array $options, int $quantity, string $currency ): array
    {
        return $this->deltas( $options, $currency );
    }

    public function describeLineOptions( Product $product, array $options ): array
    {
        $labels = [];

        foreach ( $this->storefrontOptions( $product ) as $field ) {
            $value = $options[ $field['name'] ] ?? null;

            if ( ! is_scalar( $value ) || '' === (string) $value || false === $value ) {
                continue;
            }

            $choice   = collect( $field['options'] ?? [] )->firstWhere( 'value', (string) $value );
            $labels[] = [ 'label' => $field['label'], 'value' => true === $value ? 'Yes' : (string) ( $choice['label'] ?? $value ) ];
        }

        return $labels;
    }

    public function storefrontOptions( Product $product ): array
    {
        $delta = static fn ( string $key ): array => [ 'amount' => self::DELTAS[ $key ], 'currency' => 'USD' ];

        return [
            [
                'type'     => 'swatch',
                'name'     => 'frame',
                'label'    => 'Frame',
                'required' => true,
                'options'  => [
                    [ 'value' => 'oak', 'label' => 'Black oak', 'swatch' => [ 'hex' => '#2b2118', 'alt' => 'Black oak' ], 'price_delta' => $delta( 'frame:oak' ) ],
                    [ 'value' => 'ash', 'label' => 'Natural ash', 'swatch' => [ 'hex' => '#d8c3a0', 'alt' => 'Natural ash' ] ],
                    [ 'value' => 'linen', 'label' => 'Linen wrap', 'swatch' => [ 'image_url' => 'https://example.com/linen.jpg', 'alt' => 'Linen' ] ],
                    [ 'value' => 'walnut', 'label' => 'Walnut', 'swatch' => [ 'hex' => '#5a3a22', 'alt' => 'Walnut' ], 'disabled' => true, 'hint' => 'Back in January' ],
                    [ 'value' => 'none', 'label' => 'No frame' ],
                ],
            ],
            [
                'type'     => 'radio',
                'name'     => 'size',
                'label'    => 'Size',
                'required' => true,
                'default'  => 'small',
                'help'     => 'Inches, before framing.',
                'meta'     => [ 'display' => 'cards', 'disables' => [ 'large' => [ 'paper' => [ 'rag' ] ] ] ],
                'options'  => [
                    [ 'value' => 'small', 'label' => '8 × 10', 'hint' => 'Desk or shelf', 'image' => [ 'url' => 'https://example.com/small.jpg', 'alt' => 'An 8 by 10 print on a desk' ] ],
                    [ 'value' => 'large', 'label' => '24 × 36', 'hint' => 'Statement wall', 'price_delta' => $delta( 'size:large' ) ],
                ],
            ],
            [
                'type'     => 'radio',
                'name'     => 'paper',
                'label'    => 'Paper',
                'required' => true,
                'default'  => 'matte',
                'options'  => [
                    [ 'value' => 'matte', 'label' => 'Matte' ],
                    [ 'value' => 'rag', 'label' => 'Fine art rag', 'hint' => 'Cotton, archival', 'price_delta' => $delta( 'paper:rag' ) ],
                ],
            ],
            [
                'type'         => 'select',
                'name'         => 'mat',
                'label'        => 'Mat',
                'visible_when' => [ [ 'field' => 'frame', 'in' => [ 'oak', 'ash' ] ] ],
                'options'      => [
                    [ 'value' => 'white', 'label' => 'White' ],
                    [ 'value' => 'black', 'label' => 'Black', 'price_delta' => $delta( 'mat:black' ) ],
                ],
            ],
            [ 'type' => 'checkbox', 'name' => 'gift', 'label' => 'This is a gift' ],
            [
                'type'         => 'textarea',
                'name'         => 'gift_note',
                'label'        => 'Gift note',
                'required'     => true,
                'rules'        => [ 'max:200' ],
                'visible_when' => [ [ 'field' => 'gift', 'in' => [ true ] ] ],
            ],
        ];
    }

    /**
     * The chosen choices' price differences, in `$currency` (USD only).
     *
     * @return array<int, array{label: string, amount: Money}>
     */
    protected function deltas( array $options, string $currency ): array
    {
        if ( 'USD' !== $currency ) {
            return [];
        }

        $rows = [];

        foreach ( self::DELTAS as $key => $amount ) {
            [ $field, $value ] = explode( ':', $key );

            if ( ( $options[ $field ] ?? null ) === $value ) {
                $rows[] = [ 'label' => ucfirst( $field ), 'amount' => Money::USD( $amount ) ];
            }
        }

        return $rows;
    }
}
