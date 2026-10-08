<?php

declare( strict_types=1 );

namespace Tests\Fixtures\ProductTypes;

use ArtisanPackUI\Ecommerce\Contracts\ProvidesStorefrontOptions;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\ProductTypes\AbstractProductType;
use InvalidArgumentException;

/**
 * A satellite-style product type that describes its add-to-cart fields:
 * an engraving, a font, a gift-wrap box, and an "about" note.
 */
class EngravedProductType extends AbstractProductType implements ProvidesStorefrontOptions
{
    public const KEY = 'engraved';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Engraved';
    }

    public function requiresFulfillment(): bool
    {
        return true;
    }

    public function isInventoryTracked(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $options  Raw options.
     *
     * @return array<string, mixed>
     */
    public function validateCartOptions( Product $product, array $options ): array
    {
        $out = parent::validateCartOptions( $product, $options );

        if ( ! is_string( $options['engraving']['text'] ?? null ) || '' === $options['engraving']['text'] ) {
            throw new InvalidArgumentException( 'An engraving is required.' );
        }

        $out['engraving'] = [ 'text' => $options['engraving']['text'], 'font' => (string) ( $options['engraving']['font'] ?? 'serif' ) ];
        $out['gift_wrap'] = (bool) ( $options['gift_wrap'] ?? false );

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function storefrontOptions( Product $product ): array
    {
        return [
            [ 'type' => 'info', 'name' => 'about', 'label' => 'Hand engraved', 'help' => 'Ships in 3 days.' ],
            [ 'type' => 'text', 'name' => 'engraving[text]', 'label' => 'Engraving', 'required' => true, 'rules' => [ 'max:20' ] ],
            [ 'type' => 'select', 'name' => 'engraving.font', 'label' => 'Font', 'options' => [ [ 'value' => 'serif', 'label' => 'Serif' ], [ 'value' => 'script', 'label' => 'Script' ] ], 'default' => 'serif' ],
            [ 'type' => 'checkbox', 'name' => 'gift_wrap', 'label' => 'Gift wrap' ],
            [ 'type' => 'unknown', 'name' => 'nope', 'label' => 'Dropped' ],
            [ 'type' => 'text', 'name' => 'bad name!', 'label' => 'Dropped too' ],
        ];
    }
}
