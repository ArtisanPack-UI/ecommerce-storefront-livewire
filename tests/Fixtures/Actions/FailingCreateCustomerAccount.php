<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Actions;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceStorefrontLivewire\Actions\CreateCustomerAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * An account action whose account creation always fails.
 */
class FailingCreateCustomerAccount extends CreateCustomerAccount
{
    public function create( Order $order, string $password ): Authenticatable
    {
        throw new RuntimeException( 'The user directory is down.' );
    }
}
