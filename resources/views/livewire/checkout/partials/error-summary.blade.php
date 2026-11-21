{{--
    The checkout's error summary (#136): the design-system
    ValidationSummary listing every problem on the step, each prefixed with
    its field's label (`$errorLabels`) and linked to the field. It is an
    alert keyed on `$errorSummary`, so it is re-created, announced and
    focused after each failed submit. Also used by the unpaid order screen.

    A link moves focus to the control with the field's id, name or
    `wire:model` (the library), else to the first control inside the
    field's `data-field-key` or `data-field` wrapper.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $errors->any() )
    @php
        $ecommerceCheckoutErrors = [];

        foreach ( $errors->getMessages() as $ecommerceErrorField => $ecommerceErrorMessages ) {
            $ecommerceErrorMessage = (string) ( $ecommerceErrorMessages[0] ?? '' );

            $ecommerceCheckoutErrors[ $ecommerceErrorField ] = [ isset( $errorLabels[ $ecommerceErrorField ] ) ? __( ':field: :message', [ 'field' => $errorLabels[ $ecommerceErrorField ], 'message' => $ecommerceErrorMessage ] ) : $ecommerceErrorMessage ];
        }

        $ecommerceCheckoutErrorCount = count( $ecommerceCheckoutErrors );
    @endphp
    <x-artisanpack-validation-summary
        :errors="$ecommerceCheckoutErrors"
        :title="trans_choice( 'There is :count problem to fix:|There are :count problems to fix:', $ecommerceCheckoutErrorCount, [ 'count' => $ecommerceCheckoutErrorCount ] )"
        wire:key="checkout-errors-{{ $errorSummary }}"
        id="ec-checkout-errors"
        class="ec-error-summary"
        x-on:click="if ( $event.defaultPrevented ) { return; } const link = $event.target.closest( 'a' ); if ( ! link || ! link.hash ) { return; } const key = decodeURIComponent( link.hash.slice( 1 ) ); const field = [ ...document.querySelectorAll( '[data-field-key], [data-field]' ) ].find( ( el ) => ( el.dataset.fieldKey ?? el.dataset.field ) === key ); const control = field?.querySelector( 'input:not([type=hidden]), select, textarea' ); if ( control ) { $event.preventDefault(); control.focus(); control.scrollIntoView( { block: 'center' } ); }"
        data-checkout-errors
    />
@endif
