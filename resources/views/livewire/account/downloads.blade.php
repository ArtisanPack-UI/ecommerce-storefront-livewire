{{--
    Downloads and licence keys. See Livewire\Account\Downloads.

    The copy button uses the browser's clipboard (livewire-ui-components has
    no clipboard component); a polite live region announces the copy.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-account-downloads>
    @if ( $failed )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'Your downloads couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-downloads-failed />
    @endif

    @if ( ! $failed && ( null === $downloads || 0 === $downloads->total() ) && ( null === $licences || 0 === $licences->total() ) )
        <x-artisanpack-ec-empty-state icon="o-arrow-down-tray" :title="__( 'No downloads yet' )" :description="__( 'Files and licence keys from your orders will appear here.' )" data-account-no-downloads>
            @if ( null !== $catalogUrl )
                <x-artisanpack-button :label="__( 'Start shopping' )" :link="$catalogUrl" color="primary" />
            @endif
        </x-artisanpack-ec-empty-state>
    @endif

    @if ( null !== $downloads && $downloads->total() > 0 )
        <section class="flex flex-col gap-3" aria-labelledby="ec-account-files" data-account-files>
            <h2 id="ec-account-files" class="text-xl font-bold">{{ __( 'Files' ) }}</h2>

            <ul class="flex flex-col divide-y divide-base-content/10 rounded-box border border-base-content/10">
                @foreach ( $downloads as $row )
                    <li class="flex flex-wrap items-center justify-between gap-4 p-4" wire:key="account-download-{{ $row['id'] }}" data-account-download="{{ $row['id'] }}" data-state="{{ $row['state'] }}">
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold">{{ $row['product'] }}</span>
                            <span class="text-sm">
                                {{ $row['file'] }}
                                @if ( null !== $row['version'] )
                                    &middot; {{ __( 'Version :version', [ 'version' => $row['version'] ] ) }}
                                @endif
                            </span>

                            @if ( 'expired' === $row['state'] )
                                <p class="text-sm text-error" data-account-download-reason="expired">
                                    {{ null === $row['expires'] ? __( 'This download has expired.' ) : __( 'This download expired on :date.', [ 'date' => $row['expires'] ] ) }}
                                </p>
                            @elseif ( 'exhausted' === $row['state'] )
                                <p class="text-sm text-error" data-account-download-reason="exhausted">{{ __( 'You\'ve used all the downloads for this file.' ) }}</p>
                            @else
                                <p class="text-sm text-base-content/70">
                                    {{ null === $row['remaining'] ? __( 'Unlimited downloads' ) : trans_choice( ':count download left|:count downloads left', $row['remaining'], [ 'count' => $row['remaining'] ] ) }}
                                    @if ( null !== $row['expires'] )
                                        &middot; {{ __( 'Available until :date', [ 'date' => $row['expires'] ] ) }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if ( null !== $row['url'] )
                            @if ( $row['streaming'] )
                                <x-artisanpack-button :label="__( 'Play' )" icon="o-play" :link="$row['url']" external class="btn-sm" :aria-label="__( 'Play :file (opens in a new tab)', [ 'file' => $row['file'] ] )" data-account-download-play />
                            @else
                                <x-artisanpack-button :label="__( 'Download' )" icon="o-arrow-down-tray" :link="$row['url']" no-wire-navigate class="btn-sm" color="primary" :aria-label="__( 'Download :file', [ 'file' => $row['file'] ] )" data-account-download-button />
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>

            <x-artisanpack-pagination :rows="$downloads" hide-per-page data-account-downloads-pagination />
        </section>
    @endif

    @if ( null !== $licences && $licences->total() > 0 )
        <section class="flex flex-col gap-3" aria-labelledby="ec-account-licences" data-account-licences>
            <h2 id="ec-account-licences" class="text-xl font-bold">{{ __( 'Licence keys' ) }}</h2>

            <ul class="flex flex-col divide-y divide-base-content/10 rounded-box border border-base-content/10">
                @foreach ( $licences as $row )
                    <li
                        class="flex flex-wrap items-center justify-between gap-4 p-4"
                        wire:key="account-licence-{{ $row['id'] }}"
                        x-data="{ copied: false, copy() { navigator.clipboard.writeText( @js( $row['key'] ) ).then( () => { this.copied = true; setTimeout( () => this.copied = false, 2000 ) } ) } }"
                        data-account-licence="{{ $row['id'] }}"
                        data-state="{{ $row['state'] }}"
                    >
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold">{{ $row['product'] }}</span>
                            <code class="break-all font-mono text-sm" data-account-licence-key>{{ $row['key'] }}</code>
                            <p class="text-sm text-base-content/70">
                                @if ( null === $row['limit'] )
                                    {{ trans_choice( ':count activation|:count activations', $row['activations'], [ 'count' => $row['activations'] ] ) }}
                                @else
                                    {{ __( ':count of :limit activations used', [ 'count' => $row['activations'], 'limit' => $row['limit'] ] ) }}
                                @endif

                                @if ( 'active' === $row['state'] && null !== $row['expires'] )
                                    &middot; {{ __( 'Valid until :date', [ 'date' => $row['expires'] ] ) }}
                                @endif
                            </p>

                            @if ( 'revoked' === $row['state'] )
                                <p class="text-sm text-error" data-account-licence-reason="revoked">{{ __( 'This licence key has been revoked.' ) }}</p>
                            @elseif ( 'expired' === $row['state'] )
                                <p class="text-sm text-error" data-account-licence-reason="expired">
                                    {{ null === $row['expires'] ? __( 'This licence key has expired.' ) : __( 'This licence key expired on :date.', [ 'date' => $row['expires'] ] ) }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <x-artisanpack-button :label="__( 'Copy' )" icon="o-clipboard-document" class="btn-sm" @click="copy()" :aria-label="__( 'Copy the licence key for :product', [ 'product' => $row['product'] ] )" data-account-licence-copy />
                            <span class="text-sm text-success" role="status" aria-live="polite" x-text="copied ? @js( __( 'Copied' ) ) : ''"></span>
                        </div>
                    </li>
                @endforeach
            </ul>

            <x-artisanpack-pagination :rows="$licences" hide-per-page data-account-licences-pagination />
        </section>
    @endif
</div>
