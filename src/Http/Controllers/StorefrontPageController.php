<?php

/**
 * Storefront page controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Http\Controllers;

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Returns the page view for each storefront and account screen (spec §5.1).
 *
 * Every page is a controller action (not a closure, so `route:cache` works)
 * returning a view that extends the configured layout and embeds one
 * Livewire component. Catalog subjects (category, tag, product) are resolved
 * here so an unknown or hidden one is a 404; owned records (orders) are
 * passed through unresolved for their component to load and authorize.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StorefrontPageController extends Controller
{
    /**
     * The catalog.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function catalog(): View
    {
        return view( 'ecommerce-storefront::pages.catalog' );
    }

    /**
     * A category page, found by the last slug of its chain
     * (`clothing/shirts`). A chain that isn't the category's canonical one
     * (`shirts`, `shirts/clothing`) redirects there permanently, keeping
     * the query string; an unknown slug is a 404.
     *
     * @since 1.0.0
     *
     * @param  Request        $request  The request.
     * @param  CategoryPaths  $paths    Category paths.
     * @param  string         $path     The slug chain.
     *
     * @return RedirectResponse|View
     */
    public function category( Request $request, CategoryPaths $paths, string $path ): View|RedirectResponse
    {
        $segments = explode( '/', trim( $path, '/' ) );
        $node     = $paths->bySlug( (string) end( $segments ) );
        $category = null === $node ? null : ProductCategory::query()->find( $node['id'] );

        abort_if( null === $category, 404 );

        $canonical = (string) $paths->path( (int) $category->id );

        if ( $canonical !== implode( '/', $segments ) ) {
            return redirect()->route( 'artisanpack.ecommerce.storefront.category', [ 'path' => $canonical, ...$request->query() ], 301 );
        }

        return view( 'ecommerce-storefront::pages.category', [ 'category' => $category ] );
    }

    /**
     * A tag page.
     *
     * @since 1.0.0
     *
     * @param  string  $tag  The tag slug.
     *
     * @return View
     */
    public function tag( string $tag ): View
    {
        $model = ProductTag::query()->where( 'slug', $tag )->first();

        abort_if( null === $model, 404 );

        return view( 'ecommerce-storefront::pages.tag', [ 'tag' => $model ] );
    }

    /**
     * A product page. Only storefront-visible products resolve; a product
     * whose type's satellite is gone is a 404 too (parent plan §16.6).
     *
     * @since 1.0.0
     *
     * @param  CatalogQuery  $catalog  The engine's catalog query.
     * @param  string        $product  The product slug.
     *
     * @return View
     */
    public function product( CatalogQuery $catalog, string $product ): View
    {
        $model = $catalog->productBySlug( $product );

        abort_if( null === $model || $model->typeIsMissing(), 404 );

        return view( 'ecommerce-storefront::pages.product', [ 'product' => $model ] );
    }

    /**
     * The search page.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function search(): View
    {
        return view( 'ecommerce-storefront::pages.search' );
    }

    /**
     * The cart page.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function cart(): View
    {
        return view( 'ecommerce-storefront::pages.cart' );
    }

    /**
     * The checkout.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function checkout(): View
    {
        return view( 'ecommerce-storefront::pages.checkout' );
    }

    /**
     * Where the payment provider sends the shopper back (redirects, 3-D
     * Secure).
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function checkoutReturn(): View
    {
        return view( 'ecommerce-storefront::pages.checkout-return' );
    }

    /**
     * An order's confirmation page. The component loads the order and
     * authorizes it (signed link for guests).
     *
     * @since 1.0.0
     *
     * @param  string  $order  The order reference.
     *
     * @return View
     */
    public function confirmation( string $order ): View
    {
        return view( 'ecommerce-storefront::pages.confirmation', [ 'order' => $order ] );
    }

    /**
     * The guest order lookup.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function lookup(): View
    {
        return view( 'ecommerce-storefront::pages.lookup' );
    }

    /**
     * The account dashboard.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountDashboard(): View
    {
        return view( 'ecommerce-storefront::pages.account.dashboard' );
    }

    /**
     * The shopper's order history.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountOrders(): View
    {
        return view( 'ecommerce-storefront::pages.account.orders' );
    }

    /**
     * One of the shopper's orders. The component loads the order and
     * authorizes it (another customer's order is a 404).
     *
     * @since 1.0.0
     *
     * @param  string  $order  The order reference.
     *
     * @return View
     */
    public function accountOrder( string $order ): View
    {
        return view( 'ecommerce-storefront::pages.account.order', [ 'order' => $order ] );
    }

    /**
     * The shopper's address book.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountAddresses(): View
    {
        return view( 'ecommerce-storefront::pages.account.addresses' );
    }

    /**
     * The shopper's downloads and licence keys.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountDownloads(): View
    {
        return view( 'ecommerce-storefront::pages.account.downloads' );
    }

    /**
     * The shopper's profile and notification preferences.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountProfile(): View
    {
        return view( 'ecommerce-storefront::pages.account.profile' );
    }

    /**
     * Claiming a guest order.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function accountClaim(): View
    {
        return view( 'ecommerce-storefront::pages.account.claim' );
    }
}
