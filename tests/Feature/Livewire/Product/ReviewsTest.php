<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Reviews;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\User;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerce.review.submitting' );
} );

/**
 * An approved review of `$product`.
 *
 * @param  array<string, mixed>  $attributes  Overrides.
 */
function approvedReview( Product $product, int $rating, array $attributes = [] ): ProductReview
{
    return ProductReview::factory()->approved()->create( $attributes + [
        'product_id'  => $product->id,
        'rating'      => $rating,
        'approved_at' => now(),
    ] );
}

/**
 * `$user`'s customer, with a paid order containing `$product`.
 */
function buyerOf( User $user, Product $product ): Customer
{
    $customer = app( CustomerService::class )->customerForUser( $user, true );
    $order    = Order::factory()->create( [ 'customer_id' => $customer->id, 'payment_status' => 'paid' ] );
    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => $product->id ] );

    return $customer;
}

it( 'renders the histogram, the average, and the approved reviews', function (): void {
    $product = makeProduct( 1000, [ 'name' => 'Mug' ] );

    approvedReview( $product, 5, [ 'author_name' => 'Ana', 'title' => 'Lovely', 'body' => "Holds heat.\nWashes well.", 'is_verified_purchase' => true, 'approved_at' => Carbon::parse( '2026-03-04 12:00' ) ] );
    approvedReview( $product, 5, [ 'author_name' => 'Ben' ] );
    approvedReview( $product, 2, [ 'author_name' => 'Cy' ] );
    ProductReview::factory()->create( [ 'product_id' => $product->id, 'rating' => 1, 'author_name' => 'Pending Pat' ] );

    Livewire::test( Reviews::class, [ 'product' => $product ] )
        ->assertOk()
        ->assertSeeHtml( 'id="reviews"' )
        ->assertSee( 'Customer reviews' )
        ->assertSee( '4 out of 5' )
        ->assertSee( '3 reviews' )
        ->assertSee( '5 stars: 2 reviews (67%)' )
        ->assertSee( '2 stars: 1 review (33%)' )
        ->assertSee( '1 star: 0 reviews (0%)' )
        ->assertSee( 'Ana' )
        ->assertSee( 'Lovely' )
        ->assertSee( 'March 4, 2026' )
        ->assertSee( 'Verified purchase' )
        ->assertSee( 'Rated 5 out of 5' )
        ->assertDontSee( 'Pending Pat' );
} );

it( 'says when there are no reviews yet', function (): void {
    Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )
        ->assertSee( 'No reviews yet. Be the first to review this product.' )
        ->assertDontSeeHtml( 'data-review-histogram' )
        ->assertSee( 'Write a review' );
} );

it( 'filters to one rating from the histogram and back', function (): void {
    $product = makeProduct();

    approvedReview( $product, 5, [ 'author_name' => 'Five Star Fan' ] );
    approvedReview( $product, 1, [ 'author_name' => 'One Star Critic' ] );

    Livewire::test( Reviews::class, [ 'product' => $product ] )
        ->call( 'filterRating', 1 )
        ->assertSet( 'ratingFilter', 1 )
        ->assertSee( 'One Star Critic' )
        ->assertDontSee( 'Five Star Fan' )
        ->assertSee( 'Showing 1-star reviews' )
        ->assertSee( '1 review shown' )
        ->assertSeeHtml( 'aria-pressed="true"' )
        ->call( 'filterRating', 1 )
        ->assertSet( 'ratingFilter', null )
        ->assertSee( 'Five Star Fan' )
        ->call( 'filterRating', 9 )
        ->assertSet( 'ratingFilter', null )
        ->call( 'filterRating', 'abc' )
        ->assertSet( 'ratingFilter', null )
        ->call( 'filterRating', '2' )
        ->assertSet( 'ratingFilter', 2 );
} );

it( 'ignores a bad rating from the query string', function ( mixed $rating ): void {
    $product = makeProduct();
    approvedReview( $product, 4, [ 'author_name' => 'Four' ] );

    Livewire::withQueryParams( [ 'review-rating' => $rating ] )
        ->test( Reviews::class, [ 'product' => $product ] )
        ->assertOk()
        ->assertSet( 'ratingFilter', null )
        ->assertSee( 'Four' );
} )->with( [ 'out of range' => [ 7 ], 'not a number' => [ 'abc' ], 'array' => [ [ 1 ] ] ] );

it( 'paginates the reviews', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.reviews.per_page', 2 );

    $product = makeProduct();

    foreach ( [ 'Oldest', 'Middle', 'Newest' ] as $i => $name ) {
        approvedReview( $product, 4, [ 'author_name' => $name, 'approved_at' => now()->subDays( 3 - $i ) ] );
    }

    Livewire::test( Reviews::class, [ 'product' => $product ] )
        ->assertSee( 'Newest' )
        ->assertSee( 'Middle' )
        ->assertDontSee( 'Oldest' )
        ->call( 'gotoPage', 2, Reviews::PAGE_NAME )
        ->assertSee( 'Oldest' )
        ->assertDontSee( 'Newest' );
} );

it( 'lets a guest submit a review that waits for moderation', function (): void {
    $product = makeProduct();

    Livewire::test( Reviews::class, [ 'product' => $product ] )
        ->call( 'openForm' )
        ->assertSet( 'showForm', true )
        ->assertSeeHtml( 'data-review-form' )
        ->assertSee( 'Your name' )
        ->set( 'rating', 4 )
        ->set( 'title', '<b>Great</b> mug' )
        ->set( 'body', 'Keeps tea hot.<script>alert(1)</script>' )
        ->set( 'authorName', 'Dee' )
        ->set( 'authorEmail', 'DEE@Example.test' )
        ->call( 'submit' )
        ->assertHasNoErrors()
        ->assertSet( 'submitted', true )
        ->assertSet( 'showForm', false )
        ->assertSet( 'rating', null )
        ->assertSee( 'Thanks — your review is awaiting moderation' );

    $review = ProductReview::query()->sole();

    expect( $review->status )->toBe( ProductReview::STATUS_PENDING )
        ->and( $review->rating )->toBe( 4 )
        ->and( $review->title )->toBe( 'Great mug' )
        ->and( $review->body )->toBe( 'Keeps tea hot.alert(1)' )
        ->and( $review->author_name )->toBe( 'Dee' )
        ->and( $review->author_email )->toBe( 'dee@example.test' )
        ->and( $review->customer_id )->toBeNull();
} );

it( 'marks a buyer\'s review as a verified purchase and doesn\'t ask them for a name', function (): void {
    $user     = makeUser();
    $product  = makeProduct();
    $customer = buyerOf( $user, $product );

    Livewire::actingAs( $user )
        ->test( Reviews::class, [ 'product' => $product ] )
        ->call( 'openForm' )
        ->assertDontSee( 'Your name' )
        ->set( 'rating', 5 )
        ->call( 'submit' )
        ->assertHasNoErrors()
        ->assertSet( 'submitted', true );

    $review = ProductReview::query()->sole();

    expect( $review->customer_id )->toBe( $customer->id )
        ->and( $review->is_verified_purchase )->toBeTrue();
} );

it( 'creates the customer for a signed-in shopper who has none yet', function (): void {
    $user = makeUser();

    Livewire::actingAs( $user )
        ->test( Reviews::class, [ 'product' => makeProduct() ] )
        ->call( 'openForm' )
        ->set( 'rating', 3 )
        ->call( 'submit' )
        ->assertHasNoErrors();

    expect( ProductReview::query()->sole()->customer_id )->toBe( Customer::forUser( $user )?->id )
        ->not->toBeNull();
} );

it( 'says when the review is published straight away', function (): void {
    $this->app->bind( ArtisanPackUI\Ecommerce\Contracts\ReviewModerator::class, static fn () => new class implements ArtisanPackUI\Ecommerce\Contracts\ReviewModerator {
        public function key(): string
        {
            return 'test';
        }

        public function moderate( ProductReview $review ): string
        {
            return 'approve';
        }
    } );

    Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )
        ->call( 'openForm' )
        ->set( 'rating', 5 )
        ->set( 'authorName', 'Eve' )
        ->set( 'authorEmail', 'eve@example.test' )
        ->call( 'submit' )
        ->assertSet( 'published', true )
        ->assertSee( 'Thanks — your review is published' )
        ->assertSee( 'Eve' );
} );

it( 'validates the form', function ( array $input, string $field, string $message ): void {
    $component = Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )->call( 'openForm' );

    foreach ( [ 'rating' => 4, 'authorName' => 'Dee', 'authorEmail' => 'dee@example.test', ...$input ] as $key => $value ) {
        $component->set( $key, $value );
    }

    $component->call( 'submit' )
        ->assertHasErrors( $field )
        ->assertSee( $message )
        ->assertSet( 'submitted', false );

    expect( ProductReview::query()->count() )->toBe( 0 );
} )->with( [
    'no rating'      => [ [ 'rating' => null ], 'rating', 'Choose a rating.' ],
    'rating too big' => [ [ 'rating' => 6 ], 'rating', 'Choose a rating from 1 to 5 stars.' ],
    'no name'        => [ [ 'authorName' => '' ], 'authorName', 'Enter your name.' ],
    'bad email'      => [ [ 'authorEmail' => 'nope' ], 'authorEmail', 'Enter a valid email address.' ],
    'long title'     => [ [ 'title' => str_repeat( 'a', 256 ) ], 'title', 'Keep the title under 255 characters.' ],
] );

it( 'files a honeypot submission as spam but thanks the sender as usual', function (): void {
    Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )
        ->call( 'openForm' )
        ->assertSeeHtml( 'name="website"' )
        ->set( 'rating', 5 )
        ->set( 'authorName', 'Bot' )
        ->set( 'authorEmail', 'bot@example.test' )
        ->set( 'honeypot', 'https://spam.example' )
        ->call( 'submit' )
        ->assertSet( 'submitted', true )
        ->assertSee( 'Thanks — your review is awaiting moderation' );

    expect( ProductReview::query()->sole()->status )->toBe( ProductReview::STATUS_SPAM );
} );

it( 'explains why a shopper can\'t review', function ( Closure $arrange, string $message ): void {
    $product = makeProduct();
    $user    = $arrange( $product );

    $component = null === $user ? Livewire::test( Reviews::class, [ 'product' => $product ] ) : Livewire::actingAs( $user )->test( Reviews::class, [ 'product' => $product ] );

    $component->assertSee( $message )
        ->assertDontSee( 'Write a review' )
        ->call( 'openForm' )
        ->assertSet( 'showForm', false );
} )->with( [
    'guests not allowed' => [ function (): ?User {
        config()->set( 'artisanpack.ecommerce.reviews.allow_guests', false );

        return null;
    }, 'Sign in to review this product.' ],
    'already reviewed'   => [ function ( Product $product ): User {
        $user     = makeUser();
        $customer = app( CustomerService::class )->customerForUser( $user, true );
        ProductReview::factory()->create( [ 'product_id' => $product->id, 'customer_id' => $customer->id ] );

        return $user;
    }, 'You\'ve already reviewed this product.' ],
    'purchase required'  => [ function (): User {
        config()->set( 'artisanpack.ecommerce.reviews.require_purchase', true );

        return makeUser();
    }, 'Only customers who bought this product can review it.' ],
] );

it( 'links guests to the host\'s sign-in page when they must sign in', function (): void {
    config()->set( 'artisanpack.ecommerce.reviews.allow_guests', false );

    Illuminate\Support\Facades\Route::get( '/login', static fn (): string => 'login' )->name( 'login' );

    Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )
        ->assertSeeHtml( 'href="' . url( '/login' ) . '"' );
} );

it( 'shows the engine\'s refusal when eligibility changes before submitting', function (): void {
    $user     = makeUser();
    $product  = makeProduct();
    $customer = app( CustomerService::class )->customerForUser( $user, true );

    $component = Livewire::actingAs( $user )
        ->test( Reviews::class, [ 'product' => $product ] )
        ->call( 'openForm' )
        ->set( 'rating', 4 );

    // Reviewed in another tab meanwhile.
    ProductReview::factory()->create( [ 'product_id' => $product->id, 'customer_id' => $customer->id ] );

    $component->call( 'submit' )
        ->assertSet( 'submitted', false )
        ->assertSet( 'showForm', false )
        ->assertSee( 'You\'ve already reviewed this product.' );

    expect( ProductReview::query()->count() )->toBe( 1 );
} );

it( 'says so when a filter aborts the submission', function (): void {
    addFilter( 'ap.ecommerce.review.submitting', static fn (): bool => false );

    Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )
        ->call( 'openForm' )
        ->set( 'rating', 4 )
        ->set( 'authorName', 'Dee' )
        ->set( 'authorEmail', 'dee@example.test' )
        ->call( 'submit' )
        ->assertHasErrors( 'review' )
        ->assertSee( 'Your review could not be submitted. Please try again later.' )
        ->assertSet( 'submitted', false );
} );

it( 'rate limits submissions', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.review.submit.per_ip', 1 );
    config()->set( 'artisanpack.ecommerce.reviews.allow_multiple', true );

    $component = Livewire::test( Reviews::class, [ 'product' => makeProduct() ] );

    foreach ( [ 1, 2 ] as $attempt ) {
        $component->call( 'openForm' )
            ->set( 'rating', 4 )
            ->set( 'authorName', 'Dee' )
            ->set( 'authorEmail', 'dee@example.test' )
            ->call( 'submit' );
    }

    expect( ProductReview::query()->count() )->toBe( 1 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'escapes review text', function (): void {
    $product = makeProduct();
    approvedReview( $product, 4, [ 'author_name' => '<i>Mal</i>', 'title' => '<b>T</b>', 'body' => '<img src=x onerror=alert(1)>' ] );

    Livewire::test( Reviews::class, [ 'product' => $product ] )
        ->assertDontSeeHtml( '<i>Mal</i>' )
        ->assertDontSeeHtml( '<b>T</b>' )
        ->assertDontSeeHtml( '<img src=x' );
} );

it( 'keeps the product and submission state out of the browser\'s reach', function ( string $property, mixed $value ): void {
    expect( fn () => Livewire::test( Reviews::class, [ 'product' => makeProduct() ] )->set( $property, $value ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} )->with( [
    'submitted' => [ 'submitted', true ],
    'published' => [ 'published', true ],
] );

it( 'is a product page section', function (): void {
    Livewire::test( Show::class, [ 'product' => makeProduct() ] )
        ->assertSeeLivewire( Reviews::class )
        ->assertSeeHtml( 'data-product-section="reviews"' );
} );
