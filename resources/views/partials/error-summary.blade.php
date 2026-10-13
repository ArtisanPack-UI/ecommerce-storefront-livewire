{{--
    A form's error summary: every problem, each linked to its field. It is
    keyed on the component's `$errorSummary` counter (SummarizesErrors), so
    it is re-created, announced, and focused after each failed submit.

    Expects `$summaryId` (unique on the page) and optionally
    `$summaryExcept` (error keys shown elsewhere, such as a form-level
    alert) and `$summaryClass` (extra classes, such as a grid span). Fields are found inside the form by their `data-field-key`
    (<x-artisanpack-ec-field>) or `data-field` wrapper.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceSummaryErrors = array_diff_key( $errors->getMessages(), array_flip( $summaryExcept ?? [] ) ) )
@if ( [] !== $ecommerceSummaryErrors )
    <div
        wire:key="{{ $summaryId }}-errors-{{ $errorSummary }}"
        tabindex="-1"
        role="alert"
        aria-labelledby="{{ $summaryId }}-errors-title"
        x-init="$nextTick( () => $el.focus() )"
        @class( [ 'rounded-box border border-error bg-error/10 p-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-error', $summaryClass ?? '' ] )
        data-error-summary
    >
        <p id="{{ $summaryId }}-errors-title" class="font-semibold">
            {{ trans_choice( 'There is :count problem to fix:|There are :count problems to fix:', count( $ecommerceSummaryErrors ), [ 'count' => count( $ecommerceSummaryErrors ) ] ) }}
        </p>

        <ul class="mt-2 list-disc ps-5 text-sm">
            @foreach ( $ecommerceSummaryErrors as $ecommerceErrorField => $ecommerceErrorMessages )
                <li>
                    <a
                        href="#"
                        class="link"
                        data-target="{{ $ecommerceErrorField }}"
                        x-on:click.prevent="( $el.closest( 'form' ) ?? document ).querySelector( '[data-field-key=&quot;' + $el.dataset.target + '&quot;], [data-field=&quot;' + $el.dataset.target + '&quot;]' )?.querySelector( 'input:not([type=hidden]), select, textarea' )?.focus()"
                        data-error-summary-link="{{ $ecommerceErrorField }}"
                    >{{ $ecommerceErrorMessages[0] ?? '' }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
