{{--
    Checkout step: review (#140), before payment. The steps above it
    already show the contact, addresses and shipping, and the order summary
    the lines and totals, so it holds what's left to settle: the optional
    order note (with a character count), "Create an account with this
    email" for guests when the store offers it (on the sunken surface, the
    password under it once checked), the terms checkbox with its link (when
    a terms page is set), and "Continue to payment". Confirming the payment
    on the next step places the order with what was entered here.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<form wire:submit="saveReview" novalidate class="ec-checkout-form ec-checkout-form--loose" data-checkout-review-form>
    <div data-field="orderNote">
        <x-artisanpack-ec-field
            control="textarea"
            id="ec-checkout-order-note"
            :label="__( 'Order note' )"
            optional
            :placeholder="__( 'Anything the store should know about your order' )"
            :hint="__( 'Anything the store should know about your order. Up to :max characters.', [ 'max' => $noteMaxLength ] )"
            rows="3"
            :max-length="$noteMaxLength"
            wire:model="orderNote"
            data-checkout-order-note
        />
    </div>

    @if ( $offersAccount )
        <div class="ec-checkout-callout ec-checkout-callout--stacked" data-checkout-account>
            <x-artisanpack-checkbox :label="__( 'Create an account with this email' )" :description="__( 'Track this order and check out faster next time.' )" wire:model.live="createAccount" data-checkout-create-account />

            @if ( $createAccount )
                <div class="ec-checkout-callout__nested" data-field="password">
                    <x-artisanpack-ec-field control="password" id="ec-checkout-password" :label="__( 'Password' )" autocomplete="new-password" wire:model="password" data-checkout-password />
                </div>
            @endif
        </div>
    @endif

    @if ( null !== $termsUrl )
        @php
            [ $ecommerceTermsBefore, $ecommerceTermsAfter ] = explode( ':terms', __( 'I accept the :terms' ), 2 ) + [ 1 => '' ];
        @endphp
        <div data-field="acceptTerms">
            <x-artisanpack-checkbox id="ec-checkout-terms" error-field="acceptTerms" wire:model="acceptTerms" data-checkout-terms>
                <x-slot:label>{{ $ecommerceTermsBefore }}<a href="{{ $termsUrl }}" class="apui-link" target="_blank" rel="noopener" data-checkout-terms-link>{{ __( 'terms and conditions' ) }}<span class="apui-sr-only"> {{ __( '(opens in a new tab)' ) }}</span></a>{{ $ecommerceTermsAfter }}</x-slot:label>
            </x-artisanpack-checkbox>
        </div>
    @endif

    <div class="ec-checkout-form__actions">
        <x-artisanpack-button
            type="submit"
            color="primary"
            class="ec-checkout-form__submit"
            wire:loading.attr="disabled"
            wire:target="saveReview"
            data-checkout-review-submit>{{ __( 'Continue to payment' ) }}</x-artisanpack-button>
    </div>
</form>
