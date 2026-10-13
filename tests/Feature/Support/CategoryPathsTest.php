<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;

beforeEach( function (): void {
    CategoryTree::flush();

    $this->clothing = ProductCategory::factory()->create( [ 'name' => 'Clothing', 'slug' => 'clothing' ] );
    $this->shirts   = ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->clothing->id ] );
    $this->linen    = ProductCategory::factory()->create( [ 'name' => 'Linen', 'slug' => 'linen', 'parent_id' => $this->shirts->id ] );
    $this->hats     = ProductCategory::factory()->create( [ 'name' => 'Hats', 'slug' => 'hats' ] );
} );

it( 'builds canonical slug chains and URLs', function (): void {
    $paths = app( CategoryPaths::class );

    expect( $paths->path( $this->linen->id ) )->toBe( 'clothing/shirts/linen' )
        ->and( $paths->path( $this->hats->id ) )->toBe( 'hats' )
        ->and( $paths->path( 999 ) )->toBeNull()
        ->and( $paths->url( $this->shirts->id ) )->toBe( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts' ] ) );
} );

it( 'lists ancestry, children, and descendants with relative depth', function (): void {
    $paths = app( CategoryPaths::class );

    expect( array_column( $paths->ancestry( $this->linen->id ), 'slug' ) )->toBe( [ 'clothing', 'shirts', 'linen' ] )
        ->and( array_column( $paths->children( null ), 'slug' ) )->toEqualCanonicalizing( [ 'clothing', 'hats' ] )
        ->and( array_column( $paths->children( $this->clothing->id ), 'slug' ) )->toBe( [ 'shirts' ] )
        ->and( array_map( static fn ( array $node ): array => [ $node['slug'], $node['depth'] ], $paths->descendants( $this->clothing->id ) ) )->toBe( [ [ 'shirts', 0 ], [ 'linen', 1 ] ] )
        ->and( $paths->descendants( 999 ) )->toBe( [] )
        ->and( $paths->bySlug( 'linen' )['id'] ?? null )->toBe( $this->linen->id );
} );
