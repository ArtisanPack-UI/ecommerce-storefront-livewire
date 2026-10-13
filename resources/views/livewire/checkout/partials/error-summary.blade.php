{{--
    The checkout's error summary: every problem on the step, each linked to
    its field. It is re-created (and takes focus) after each failed submit.

    Fields are found by their `data-field` wrapper, since the form
    components generate their own input ids.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $errors->any() )
    <div
        wire:key="checkout-errors-{{ $errorSummary }}"
        tabindex="-1"
        role="alert"
        aria-labelledby="ec-checkout-errors-title"
        x-init="$nextTick( () => $el.focus() )"
        class="rounded-box border border-error bg-error/10 p-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-error"
        data-checkout-errors
    >
        <h2 id="ec-checkout-errors-title" class="font-semibold">
            {{ trans_choice( 'There is :count problem to fix:|There are :count problems to fix:', count( $errors->keys() ), [ 'count' => count( $errors->keys() ) ] ) }}
        </h2>

        <ul class="mt-2 list-disc ps-5 text-sm">
            @foreach ( $errors->getMessages() as $ecommerceErrorField => $ecommerceErrorMessages )
                <li>
                    <a
                        href="#"
                        class="link"
                        data-target="{{ $ecommerceErrorField }}"
                        x-on:click.prevent="document.querySelector( '[data-field=&quot;' + $el.dataset.target + '&quot;]' )?.querySelector( 'input, select, textarea' )?.focus()"
                        data-checkout-error="{{ $ecommerceErrorField }}"
                    >
                        @if ( isset( $errorLabels[ $ecommerceErrorField ] ) )
                            <span class="font-semibold">{{ $errorLabels[ $ecommerceErrorField ] }}:</span>
                        @endif
                        {{ $ecommerceErrorMessages[0] ?? '' }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
