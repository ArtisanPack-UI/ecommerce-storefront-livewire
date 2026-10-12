<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use Illuminate\Auth\MustVerifyEmail as VerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * A host-app user whose model verifies emails (`email_verified_at`).
 */
class VerifiedUser extends User implements MustVerifyEmail
{
    use VerifiesEmail;
}
