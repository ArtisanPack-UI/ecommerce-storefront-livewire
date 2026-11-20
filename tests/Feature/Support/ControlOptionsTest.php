<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions;

it( 'maps the 2.x option keys onto the 3.0 shape', function (): void {
    expect( ControlOptions::from( [ [ 'id' => 'USD', 'name' => 'US Dollar', 'hint' => '$' ] ] ) )
        ->toBe( [ [ 'value' => 'USD', 'label' => 'US Dollar', 'description' => '$' ] ] );
} );

it( 'keeps options already in the 3.0 shape, extra keys and plain strings', function (): void {
    expect( ControlOptions::from( [ [ 'value' => 'a', 'label' => 'A', 'disabled' => true ], 'b' ] ) )
        ->toEqual( [ [ 'value' => 'a', 'label' => 'A', 'disabled' => true ], 'b' ] );
} );

it( 'maps a select group recursively and takes null', function (): void {
    expect( ControlOptions::from( [ [ 'name' => 'Group', 'options' => [ [ 'id' => 1, 'name' => 'One' ] ] ] ] ) )
        ->toBe( [ [ 'label' => 'Group', 'options' => [ [ 'value' => 1, 'label' => 'One' ] ] ] ] )
        ->and( ControlOptions::from( null ) )->toBe( [] );
} );
