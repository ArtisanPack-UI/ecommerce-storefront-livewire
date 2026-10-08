<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\CategoryShow;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\TagShow;
use Livewire\Livewire;

beforeEach( function (): void {
    CategoryTree::flush();

    $this->clothing = ProductCategory::factory()->create( [ 'name' => 'Clothing', 'slug' => 'clothing', 'description' => '<p>All our <strong>clothes</strong>.</p><script>alert(1)</script>' ] );
    $this->shirts   = ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->clothing->id ] );
    $this->linen    = ProductCategory::factory()->create( [ 'name' => 'Linen', 'slug' => 'linen', 'parent_id' => $this->shirts->id ] );
} );

it( 'renders the header, breadcrumbs, and sub-category chips', function (): void {
    Livewire::test( CategoryShow::class, [ 'category' => $this->shirts ] )
        ->assertOk()
        ->assertSeeHtml( '<h1 class="text-3xl font-bold">Shirts</h1>' )
        ->assertSeeHtml( 'aria-label="Breadcrumb"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ) . '"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts/linen' ] ) . '"' )
        ->assertSeeHtml( 'data-subcategories' )
        ->assertSeeLivewire( Index::class );
} );

it( 'renders the description through kses', function (): void {
    Livewire::test( CategoryShow::class, [ 'category' => $this->clothing ] )
        ->assertSeeHtml( '<strong>clothes</strong>' )
        ->assertDontSeeHtml( '<script>' );
} );

it( 'leaves out empty parts', function (): void {
    Livewire::test( CategoryShow::class, [ 'category' => $this->linen->fresh() ] )
        ->assertDontSeeHtml( 'data-subcategories' )
        ->assertDontSeeHtml( 'data-category-description' )
        ->assertDontSeeHtml( 'data-category-image' );
} );

it( 'lists products from the category and its descendants', function (): void {
    makeProduct( 2000, [ 'name' => 'Linen shirt' ] )->categories()->attach( $this->linen->id );
    makeProduct( 2000, [ 'name' => 'Wool scarf' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ) )
        ->assertOk()
        ->assertSee( 'Linen shirt' )
        ->assertDontSee( 'Wool scarf' );
} );

it( 'keeps the catalog filters and sorts on a category page', function (): void {
    makeProduct( 2000, [ 'name' => 'Linen shirt' ] )->categories()->attach( $this->linen->id );
    makeProduct( 3000, [ 'name' => 'Oxford shirt' ] )->categories()->attach( $this->shirts->id );

    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts', 'category' => 'linen', 'sort' => 'name' ] ) )
        ->assertOk()
        ->assertSee( 'Linen shirt' )
        ->assertDontSee( 'Oxford shirt' );
} );

it( 'renders a tag page with the tag name and its products', function (): void {
    $tag = ProductTag::factory()->create( [ 'name' => 'Summer', 'slug' => 'summer' ] );
    makeProduct( 2000, [ 'name' => 'Sun hat' ] )->tags()->attach( $tag->id );
    makeProduct( 2000, [ 'name' => 'Wool scarf' ] );

    Livewire::test( TagShow::class, [ 'tag' => $tag ] )
        ->assertOk()
        ->assertSeeHtml( '<h1 class="text-3xl font-bold">Summer</h1>' )
        ->assertSeeLivewire( Index::class );

    $this->get( route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'summer' ] ) )
        ->assertSee( 'Sun hat' )
        ->assertDontSee( 'Wool scarf' );
} );

it( 'escapes category and tag names', function (): void {
    $category = ProductCategory::factory()->create( [ 'name' => '<b>Bold</b>', 'slug' => 'bold' ] );
    $tag      = ProductTag::factory()->create( [ 'name' => '<i>Tilt</i>', 'slug' => 'tilt' ] );

    Livewire::test( CategoryShow::class, [ 'category' => $category ] )->assertDontSeeHtml( '<b>Bold</b>' );
    Livewire::test( TagShow::class, [ 'tag' => $tag ] )->assertDontSeeHtml( '<i>Tilt</i>' );
} );
