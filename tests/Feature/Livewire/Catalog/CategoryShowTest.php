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
        ->assertSeeHtml( '<h1 class="ec-category-hero__title">Shirts</h1>' )
        ->assertSeeHtml( 'aria-label="Breadcrumb"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ) . '"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts/linen' ] ) . '"' )
        ->assertSeeHtml( 'data-subcategories' )
        ->assertSeeLivewire( Index::class );
} );

it( 'heads the category with a design-system card: eyebrow, display name, description and chips', function (): void {
    $html = Livewire::test( CategoryShow::class, [ 'category' => $this->clothing ] )->html();

    preg_match( '#<div class="apui-card ec-category-hero" data-surface="default"\s*data-category-header>.*?<livewire#s', preg_replace( '/<!--\[if (?:END)?BLOCK\]><!\[endif\]-->/', '', $html ) . '<livewire', $card );

    expect( $card )->not->toBeEmpty()
        ->and( $card[0] )->toContain( '<p class="ec-category-hero__eyebrow">Category</p>' )
        ->toContain( '<h1 class="ec-category-hero__title">Clothing</h1>' )
        ->toContain( 'class="ec-category-hero__description" data-category-description' )
        ->toMatch( '#<a href="' . preg_quote( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ), '#' ) . '" class="ec-chip" data-variant="select" aria-current="page" data-subcategory-all>All Clothing</a>#' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts' ] ) . '" class="ec-chip" data-variant="select" data-subcategory="' . $this->shirts->id . '">Shirts</a>' )
        ->not->toContain( 'data-with-image' )
        // One banner landmark per page: the card is no <header>.
        ->and( $html )->not->toContain( '<header' )
        ->and( classTokens( $html ) )->not->toContain( 'text-3xl', 'font-bold', 'prose', 'rounded-box', 'sm:flex-row', 'sm:w-48', 'object-cover', 'flex', 'flex-col', 'gap-6' );
} );

it( 'puts the category image on the end half of the card', function (): void {
    $html = view( 'ecommerce-storefront::livewire.catalog.category-show', [
        'category'      => $this->clothing,
        'breadcrumbs'   => [ [ 'label' => 'Clothing' ] ],
        'url'           => null,
        'subcategories' => [],
        'description'   => null,
        'image'         => [ 'url' => 'https://cdn.example.test/clothing.jpg', 'srcset' => null, 'alt' => 'Folded shirts', 'width' => 1200, 'height' => 800 ],
    ] )->render();

    expect( $html )->toMatch( '#class="apui-card ec-category-hero" data-surface="default"\s+data-with-image#' )
        ->toContain( '<div class="ec-category-hero__media">' )
        ->toContain( 'alt="Folded shirts"' )
        ->toContain( 'data-category-image' )
        ->not->toContain( 'data-subcategories' );
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
