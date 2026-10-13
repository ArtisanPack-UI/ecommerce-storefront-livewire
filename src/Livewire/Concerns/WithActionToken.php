<?php

/**
 * One-time action token concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\IdempotencyConflictException;
use ArtisanPackUI\Ecommerce\Support\IdempotentAction;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ActionTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Makes "Place order" and other money-moving actions idempotent (spec §5.2).
 *
 * Mint a token when the button renders, pass it back as the action's
 * argument, and disable the button while the request runs:
 *
 * ```blade
 * <x-artisanpack-button
 *     wire:click="placeOrder( '{{ $this->actionToken( 'place-order', $cart ) }}' )"
 *     wire:loading.attr="disabled"
 * />
 * ```
 *
 * The action wraps its work in `withActionToken()`, which runs it through the
 * engine's `IdempotentAction` keyed by the token: the first request runs it,
 * and a repeat (a double click, a retried request) gets the stored result
 * without running it again. A call that throws isn't stored, so the shopper
 * can retry. The result must be something `IdempotentAction` can store:
 * null, a scalar, an array, an Eloquent model or collection, or a
 * `ReplayableResult`.
 *
 * Uses {@see SendsToasts}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait WithActionToken
{
    /**
     * Mints a token for an action, optionally bound to a subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name, e.g. `place-order`.
     * @param  mixed|null  $subject  A model, or a key, the token is limited to.
     *
     * @return string
     */
    protected function actionToken( string $action, mixed $subject = null ): string
    {
        return ActionTokens::mint( $this->actionTokenScope( $action, $subject ) );
    }

    /**
     * Runs `$action` once per token and returns its result; a repeat with
     * the same token returns the first result.
     *
     * Returns null without running `$write` when the token is invalid or
     * expired, or while the first request with it is still running; the
     * shopper gets a notice instead.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  string               $token    The token the client sent back.
     * @param  string               $action   The action name it was minted for.
     * @param  callable(): TResult  $write    The action.
     * @param  mixed|null           $subject  The subject it was minted for.
     *
     * @return TResult|null
     */
    protected function withActionToken( string $token, string $action, callable $write, mixed $subject = null ): mixed
    {
        $scope = $this->actionTokenScope( $action, $subject );

        if ( ! ActionTokens::isValid( $scope, $token ) ) {
            $this->toastWarning(
                __( 'This page has expired.' ),
                __( 'Reload the page and try again.' ),
            );

            return null;
        }

        try {
            return app( IdempotentAction::class )->run(
                Str::limit( 'ecommerce-storefront.' . $action . ':' . $this->actionSubjectKey( $subject ), 191, '' ),
                hash( 'sha256', $token ),
                $write,
            );
        } catch ( IdempotencyConflictException ) {
            $this->toastWarning(
                __( 'Still working on it.' ),
                __( 'Your earlier request is still being processed. Wait a moment before trying again.' ),
            );

            return null;
        }
    }

    /**
     * The scope a token is bound to: this component, the action, and the subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name.
     * @param  mixed|null  $subject  The subject.
     *
     * @return string
     */
    private function actionTokenScope( string $action, mixed $subject ): string
    {
        return static::class . '|' . $action . '|' . $this->actionSubjectKey( $subject );
    }

    /**
     * A stable key for the subject.
     *
     * @since 1.0.0
     *
     * @param  mixed  $subject  The subject.
     *
     * @return string
     */
    private function actionSubjectKey( mixed $subject ): string
    {
        return match ( true ) {
            $subject instanceof Model => class_basename( $subject ) . ':' . $subject->getKey(),
            is_scalar( $subject )     => (string) $subject,
            default                   => '',
        };
    }
}
