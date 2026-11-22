{{--
    A form's error summary (#85): the design-system ValidationSummary,
    titled with how many things to fix, each error a link to its field.
    Fields keep their own inline errors too. It is an alert keyed on the
    component's `$errorSummary` counter (SummarizesErrors), so it is
    re-created, announced, and focused after each failed submit.

    Expects `$summaryId` (unique on the page) and optionally
    `$summaryExcept` (error keys shown elsewhere, such as a form-level
    alert), `$summaryClass` (extra classes, such as a grid span) and
    `$summaryFieldIds` (error key => the field's id, when the ids differ
    from the keys, so each link targets the form's own field; since 2.0.0).

    Each link targets `#{key}`: the library moves focus to the element with
    that id, or the control with that name or `wire:model`. When it finds
    none, the click falls through to the root, which looks inside the form
    for the field's `data-field-key` (<x-artisanpack-ec-field>) or
    `data-field` wrapper and focuses its first control.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceSummaryErrors = array_diff_key( $errors->getMessages(), array_flip( $summaryExcept ?? [] ) );
    $ecommerceSummaryCount  = count( $ecommerceSummaryErrors );

    if ( [] !== ( $summaryFieldIds ?? [] ) ) {
        $ecommerceSummaryErrors = array_map(
            static fn ( string $key, array $messages ): array => [ 'field' => (string) ( $summaryFieldIds[ $key ] ?? $key ), 'message' => (string) ( $messages[0] ?? '' ) ],
            array_keys( $ecommerceSummaryErrors ),
            array_values( $ecommerceSummaryErrors ),
        );
    }
@endphp
@if ( [] !== $ecommerceSummaryErrors )
    <x-artisanpack-validation-summary
        :errors="$ecommerceSummaryErrors"
        :title="trans_choice( 'Fix :count thing to continue|Fix :count things to continue', $ecommerceSummaryCount, [ 'count' => $ecommerceSummaryCount ] )"
        wire:key="{{ $summaryId }}-errors-{{ $errorSummary ?? 0 }}"
        id="{{ $summaryId }}-errors"
        :class="trim( 'ec-error-summary ' . ( $summaryClass ?? '' ) )"
        x-on:click="if ( $event.defaultPrevented ) { return; } const link = $event.target.closest( 'a' ); if ( ! link || ! link.hash ) { return; } const key = decodeURIComponent( link.hash.slice( 1 ) ); const field = [ ...( $el.closest( 'form' ) ?? document ).querySelectorAll( '[data-field-key], [data-field]' ) ].find( ( el ) => ( el.dataset.fieldKey ?? el.dataset.field ) === key ); const control = field?.querySelector( 'input:not([type=hidden]), select, textarea' ); if ( control ) { $event.preventDefault(); control.focus(); control.scrollIntoView( { block: 'center' } ); }"
        data-error-summary
    />
@endif
