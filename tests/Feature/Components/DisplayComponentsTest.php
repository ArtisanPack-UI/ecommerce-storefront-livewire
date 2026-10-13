<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;

describe( 'money', function (): void {
    it( 'formats minor units in the app locale', function (): void {
        $this->blade( '<x-artisanpack-ec-money :amount="123456" currency="EUR" />' )->assertSee( '€1,234.56' );

        app()->setLocale( 'fr' );

        $this->blade( '<x-artisanpack-ec-money :amount="123456" currency="EUR" />' )->assertSee( '234,56' );
    } );

    it( 'renders a dash with spoken text for a missing amount', function (): void {
        $this->blade( '<x-artisanpack-ec-money :amount="null" />' )
            ->assertSee( '&mdash;', false )
            ->assertSee( 'No amount' );
    } );
} );

describe( 'stock status', function (): void {
    it( 'pairs every state with text and an icon, never colour alone', function ( string $status, string $text ): void {
        $this->blade( '<x-artisanpack-ec-stock-status :status="$status" />', [ 'status' => new StockStatus( $status ) ] )
            ->assertSee( $text )
            ->assertSee( 'data-stock-status="' . $status . '"', false )
            ->assertSee( '<svg', false );
    } )->with( [
        'in stock'     => [ StockStatus::IN_STOCK, 'In stock' ],
        'low stock'    => [ StockStatus::LOW_STOCK, 'Low stock' ],
        'backorder'    => [ StockStatus::BACKORDER, 'Available on backorder' ],
        'out of stock' => [ StockStatus::OUT_OF_STOCK, 'Out of stock' ],
    ] );

    it( 'says how many are left when the store shows stock counts', function (): void {
        config()->set( 'artisanpack.ecommerce-storefront-livewire.catalog.show_stock_count', true );

        $this->blade( '<x-artisanpack-ec-stock-status :status="$status" />', [ 'status' => new StockStatus( StockStatus::LOW_STOCK, 2 ) ] )
            ->assertSee( 'Only 2 left' );

        $this->blade( '<x-artisanpack-ec-stock-status :status="$status" />', [ 'status' => new StockStatus( StockStatus::LOW_STOCK, 1 ) ] )
            ->assertSee( 'Only 1 left' );
    } );

    it( 'keeps the count hidden unless the store shows stock counts', function (): void {
        $this->blade( '<x-artisanpack-ec-stock-status :status="$status" />', [ 'status' => new StockStatus( StockStatus::LOW_STOCK, 2 ) ] )
            ->assertSee( 'Low stock' )
            ->assertDontSee( 'Only 2 left' );
    } );

    it( 'looks the state up from a product', function (): void {
        $product = makeProduct();
        InventoryItem::factory()->create( [
            'stockable_type'   => $product->getMorphClass(),
            'stockable_id'     => $product->id,
            'track_inventory'  => true,
            'allow_backorder'  => false,
            'quantity_on_hand' => 0,
        ] );

        $this->blade( '<x-artisanpack-ec-stock-status :product="$product" />', [ 'product' => $product ] )
            ->assertSee( 'Out of stock' );
    } );
} );

describe( 'rating summary', function (): void {
    it( 'reads the rating aloud and links the review count', function (): void {
        $this->blade( '<x-artisanpack-ec-rating-summary :rating="4.46" :count="12" href="/p#reviews" />' )
            ->assertSee( 'Rated 4.5 out of 5' )
            ->assertSee( '12 reviews' )
            ->assertSee( 'href="/p#reviews"', false )
            ->assertSee( 'aria-hidden="true"', false );

        expect( substr_count( (string) $this->blade( '<x-artisanpack-ec-rating-summary :rating="4" :count="3" />' ), 'text-warning' ) )->toBe( 4 );
    } );

    it( 'uses the singular for one review and the locale\'s decimal mark', function (): void {
        app()->setLocale( 'de' );

        $this->blade( '<x-artisanpack-ec-rating-summary :rating="3.5" :count="1" />' )
            ->assertSee( '3,5' )
            ->assertSee( '1 Bewertung' );
    } );

    it( 'says there are no reviews, or renders nothing when asked', function (): void {
        $this->blade( '<x-artisanpack-ec-rating-summary :rating="0" :count="0" />' )->assertSee( 'No reviews yet' );

        expect( trim( (string) $this->blade( '<x-artisanpack-ec-rating-summary :rating="0" :count="0" hide-empty />' ) ) )->toBe( '' );
    } );
} );

describe( 'address', function (): void {
    it( 'formats an address with the country name and a region from its code', function (): void {
        $address = Address::fromArray( [
            'first_name'   => 'Ada',
            'last_name'    => 'Lovelace',
            'address1'     => '1 Main St',
            'city'         => 'Springfield',
            'region_code'  => 'IL',
            'postal_code'  => '62701',
            'country_code' => 'US',
            'phone'        => '555-0100',
        ] );

        $this->blade( '<x-artisanpack-ec-address :address="$address" />', [ 'address' => $address ] )
            ->assertSee( 'Ada Lovelace' )
            ->assertSee( 'Springfield, Illinois 62701' )
            ->assertSee( 'United States' )
            ->assertSee( '555-0100' );
    } );

    it( 'prefers the typed region and hides the phone when asked', function (): void {
        $this->blade( '<x-artisanpack-ec-address :address="$address" :show-phone="false" />', [ 'address' => [
            'address1'     => '10 Rue de Rivoli',
            'city'         => 'Paris',
            'region'       => 'Île-de-France',
            'postal_code'  => '75001',
            'country_code' => 'FR',
            'phone'        => '01 23 45 67 89',
        ] ] )
            ->assertSee( 'Paris, Île-de-France 75001' )
            ->assertDontSee( '01 23 45 67 89' );
    } );

    it( 'says when there is no address', function (): void {
        $this->blade( '<x-artisanpack-ec-address :address="null" />' )->assertSee( 'No address' );
    } );
} );

describe( 'empty state and skeleton', function (): void {
    it( 'announces the empty state as a status with its actions', function (): void {
        $this->blade( '<x-artisanpack-ec-empty-state title="Nothing here" description="Try again later."><a href="/shop">Shop</a></x-artisanpack-ec-empty-state>' )
            ->assertSee( 'role="status"', false )
            ->assertSee( 'Nothing here' )
            ->assertSee( 'Try again later.' )
            ->assertSee( 'href="/shop"', false );
    } );

    it( 'draws busy placeholders with a spoken loading message', function (): void {
        $view = $this->blade( '<x-artisanpack-ec-skeleton variant="card" :count="3" />' );

        $view->assertSee( 'aria-busy="true"', false )
            ->assertSee( 'Loading…' );

        expect( substr_count( (string) $view, 'skeleton aspect-square' ) )->toBe( 3 );
    } );

    it( 'falls back to text lines for an unknown variant and clamps the count', function (): void {
        $view = $this->blade( '<x-artisanpack-ec-skeleton variant="bogus" :count="0" />' );

        $view->assertSee( 'data-skeleton="text"', false );
        expect( substr_count( (string) $view, 'skeleton h-4' ) )->toBe( 1 );
    } );
} );
