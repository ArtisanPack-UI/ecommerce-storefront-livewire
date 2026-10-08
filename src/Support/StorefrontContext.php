<?php

/**
 * Storefront context.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;

/**
 * What the current page is about (spec §11.2): the product, category, or
 * order a storefront page controller resolved before rendering.
 *
 * Bound per request (scoped). Visual-editor blocks only receive their
 * attributes, so a block on a product template reads the product from
 * here:
 *
 * ```php
 * $product = app( StorefrontContext::class )->product();
 * ```
 *
 * Hosts that route storefront pages themselves set it the same way before
 * rendering a template.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StorefrontContext
{
    /**
     * The product being viewed.
     *
     * @since 1.0.0
     *
     * @var Product|null
     */
    protected ?Product $product = null;

    /**
     * The category being viewed.
     *
     * @since 1.0.0
     *
     * @var ProductCategory|null
     */
    protected ?ProductCategory $category = null;

    /**
     * The order being viewed.
     *
     * @since 1.0.0
     *
     * @var Order|null
     */
    protected ?Order $order = null;

    /**
     * Sets the product being viewed.
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  The product.
     *
     * @return static
     */
    public function setProduct( ?Product $product ): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * The product being viewed.
     *
     * @since 1.0.0
     *
     * @return Product|null
     */
    public function product(): ?Product
    {
        return $this->product;
    }

    /**
     * Sets the category being viewed.
     *
     * @since 1.0.0
     *
     * @param  ProductCategory|null  $category  The category.
     *
     * @return static
     */
    public function setCategory( ?ProductCategory $category ): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * The category being viewed.
     *
     * @since 1.0.0
     *
     * @return ProductCategory|null
     */
    public function category(): ?ProductCategory
    {
        return $this->category;
    }

    /**
     * Sets the order being viewed (only after it has been authorized).
     *
     * @since 1.0.0
     *
     * @param  Order|null  $order  The order.
     *
     * @return static
     */
    public function setOrder( ?Order $order ): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * The order being viewed.
     *
     * @since 1.0.0
     *
     * @return Order|null
     */
    public function order(): ?Order
    {
        return $this->order;
    }
}
