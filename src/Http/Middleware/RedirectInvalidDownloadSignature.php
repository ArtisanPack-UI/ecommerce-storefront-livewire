<?php

/**
 * Download-link signature middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Http\Middleware;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks a download link's signature like `ValidateSignature`, but sends a
 * shopper whose link has expired (or was altered) back to the downloads
 * page with a toast, instead of the framework's bare 403. The download
 * isn't spent, and the page gives them a fresh link.
 *
 * Only for the file route, which the browser opens as a page; the stream
 * route keeps `ValidateSignature`, since a media player can't follow a
 * redirect to a page.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
class RedirectInvalidDownloadSignature extends ValidateSignature
{
    /**
     * Handles the request.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next     The next handler.
     * @param  mixed    ...$args  `relative`, as for `ValidateSignature`.
     *
     * @return Response
     */
    public function handle( $request, Closure $next, ...$args ): Response
    {
        try {
            return parent::handle( $request, $next, ...$args );
        } catch ( InvalidSignatureException $exception ) {
            if ( ! Route::has( 'artisanpack.ecommerce.account.downloads' ) ) {
                throw $exception;
            }

            session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'warning', __( 'This download link has expired' ), __( 'Try again from your downloads.' ) ) );

            $response = redirect()->route( 'artisanpack.ecommerce.account.downloads' );
            $response->headers->set( 'Cache-Control', 'no-store, private' );
            $response->headers->set( 'Referrer-Policy', 'same-origin' );

            return $response;
        }
    }
}
