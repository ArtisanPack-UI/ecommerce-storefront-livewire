{{--
    Downloads and licence keys (#149). See Livewire\Account\Downloads.

    The lead line, then two Cards. Files: a divided List, each row a file
    (or play, for a stream) icon tile, the file's name, then its version,
    downloads left, expiry and order (or why it can't be downloaded), with
    an outline "Download" or "Play" (opens in a new tab) at the end, or an
    "Expired" / "No downloads left" Badge. Licence keys: each key's product
    and tier with a state Badge ("Valid until …", "Active", "Expired",
    "Revoked"), then the key in the design-system Clipboard field with its
    "Copy" button, and the activations; a revoked or expired key says so
    instead. No files and no keys is the EmptyState in a Card.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-downloads" data-account-downloads>
    <p class="ec-downloads__lead">{{ __( 'Files and licence keys from your orders.' ) }}</p>

    @if ( $failed )
        <x-artisanpack-alert color="warning" icon="circle-alert" :title="__( 'Your downloads couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-downloads-failed />
    @endif

    @if ( ! $failed && ( null === $downloads || 0 === $downloads->total() ) && ( null === $licences || 0 === $licences->total() ) )
        <x-artisanpack-ec-empty-state card icon="download" :title="__( 'No downloads yet' )" :description="__( 'Files and licence keys from your orders will appear here.' )" data-account-no-downloads>
            @if ( null !== $catalogUrl )
                <x-artisanpack-button :href="$catalogUrl" color="primary">{{ __( 'Start shopping' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-empty-state>
    @endif

    @if ( null !== $downloads && $downloads->total() > 0 )
        <x-artisanpack-card class="ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-account-files" data-account-files>
            <h2 id="ec-account-files" class="ec-order-card__title ec-downloads__card-title">{{ __( 'Files' ) }}</h2>

            <x-artisanpack-list variant="divided" class="ec-downloads__files">
                @foreach ( $downloads as $row )
                    <x-artisanpack-list-item class="ec-downloads__file" wire:key="account-download-{{ $row['id'] }}" data-account-download="{{ $row['id'] }}" data-state="{{ $row['state'] }}">
                        <x-slot:leading>
                            <x-artisanpack-icon :name="$row['streaming'] ? 'play' : 'file-text'" variant="tile" color="neutral" />
                        </x-slot:leading>

                        <x-slot:title>
                            <span class="ec-downloads__name">{{ $row['file'] }}</span>
                        </x-slot:title>

                        <x-slot:description>
                            @if ( 'expired' === $row['state'] )
                                <span data-account-download-reason="expired">{{ null === $row['expires'] ? __( 'This download has expired.' ) : __( 'This download expired on :date.', [ 'date' => $row['expires'] ] ) }}</span>
                            @elseif ( 'exhausted' === $row['state'] )
                                <span data-account-download-reason="exhausted">{{ __( 'You\'ve used all the downloads for this file.' ) }}</span>
                            @else
                                @php
                                    $ecommerceDownloadFacts = array_filter( [
                                        $row['streaming'] ? __( 'Streaming' ) : null,
                                        ! $row['streaming'] && null !== $row['version'] ? __( 'Version :version', [ 'version' => $row['version'] ] ) : null,
                                        $row['streaming'] ? null : ( null === $row['remaining'] ? __( 'Unlimited downloads' ) : trans_choice( ':count download left|:count downloads left', $row['remaining'], [ 'count' => $row['remaining'] ] ) ),
                                        null !== $row['expires'] ? __( 'Available until :date', [ 'date' => $row['expires'] ] ) : null,
                                        null !== $row['order'] ? __( 'Order :number', [ 'number' => $row['order'] ] ) : null,
                                    ] );
                                @endphp
                                @foreach ( $ecommerceDownloadFacts as $ecommerceDownloadFact )
                                    @unless ( $loop->first )<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>@endunless{{ $ecommerceDownloadFact }}
                                @endforeach
                            @endif
                        </x-slot:description>

                        <x-slot:trailing>
                            @if ( null !== $row['url'] )
                                @if ( $row['streaming'] )
                                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="external-link" :href="$row['url']" target="_blank" rel="noopener" :aria-label="__( 'Play :file (opens in a new tab)', [ 'file' => $row['file'] ] )" data-account-download-play>{{ __( 'Play' ) }}</x-artisanpack-button>
                                @else
                                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="download" :href="$row['url']" :aria-label="__( 'Download :file', [ 'file' => $row['file'] ] )" data-account-download-button>{{ __( 'Download' ) }}</x-artisanpack-button>
                                @endif
                            @elseif ( 'expired' === $row['state'] )
                                <x-artisanpack-badge color="neutral" size="sm" data-account-download-badge="expired">{{ __( 'Expired' ) }}</x-artisanpack-badge>
                            @elseif ( 'exhausted' === $row['state'] )
                                <x-artisanpack-badge color="neutral" size="sm" data-account-download-badge="exhausted">{{ __( 'No downloads left' ) }}</x-artisanpack-badge>
                            @endif
                        </x-slot:trailing>
                    </x-artisanpack-list-item>
                @endforeach
            </x-artisanpack-list>

            @if ( $downloads->hasPages() )
                <div class="ec-downloads__pagination">
                    <x-artisanpack-pagination :rows="$downloads" data-account-downloads-pagination />
                </div>
            @endif
        </x-artisanpack-card>
    @endif

    @if ( null !== $licences && $licences->total() > 0 )
        <x-artisanpack-card class="ec-order-card" role="region" aria-labelledby="ec-account-licences" data-account-licences>
            <h2 id="ec-account-licences" class="ec-order-card__title">{{ __( 'Licence keys' ) }}</h2>

            <ul class="ec-downloads__licences">
                @foreach ( $licences as $row )
                    <li class="ec-downloads__licence" wire:key="account-licence-{{ $row['id'] }}" data-account-licence="{{ $row['id'] }}" data-state="{{ $row['state'] }}">
                        <div class="ec-downloads__licence-head">
                            <h3 id="ec-account-licence-{{ $row['id'] }}" @class( [ 'ec-downloads__licence-name', 'ec-downloads__licence-name--inactive' => 'active' !== $row['state'] ] )>
                                {{ $row['product'] }}@if ( null !== $row['variant'] )<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ $row['variant'] }}@endif
                            </h3>

                            @if ( 'revoked' === $row['state'] )
                                <x-artisanpack-badge color="error" size="sm" data-account-licence-badge="revoked">{{ __( 'Revoked' ) }}</x-artisanpack-badge>
                            @elseif ( 'expired' === $row['state'] )
                                <x-artisanpack-badge color="neutral" size="sm" data-account-licence-badge="expired">{{ __( 'Expired' ) }}</x-artisanpack-badge>
                            @else
                                <x-artisanpack-badge color="success" size="sm" data-account-licence-badge="active">{{ null === $row['expires'] ? __( 'Active' ) : __( 'Valid until :date', [ 'date' => $row['expires'] ] ) }}</x-artisanpack-badge>
                            @endif
                        </div>

                        @if ( 'revoked' === $row['state'] )
                            <p class="ec-downloads__licence-note" data-account-licence-reason="revoked">{{ __( 'This licence key has been revoked.' ) }}</p>
                        @elseif ( 'expired' === $row['state'] )
                            <p class="ec-downloads__licence-note" data-account-licence-reason="expired">
                                {{ null === $row['expires'] ? __( 'This licence key has expired.' ) : __( 'This licence key expired on :date.', [ 'date' => $row['expires'] ] ) }}
                            </p>
                        @else
                            {{-- The group names the Copy button after its licence. --}}
                            <div role="group" aria-labelledby="ec-account-licence-{{ $row['id'] }}">
                                <x-artisanpack-clipboard variant="field" size="md" :value="$row['key']" class="ec-downloads__key" data-account-licence-key data-account-licence-copy />
                            </div>
                        @endif

                        @if ( 'revoked' !== $row['state'] )
                            <p class="ec-downloads__licence-note" data-account-licence-activations>
                                @if ( null === $row['limit'] )
                                    {{ trans_choice( ':count activation|:count activations', $row['activations'], [ 'count' => $row['activations'] ] ) }}
                                @else
                                    {{ trans_choice( ':count of :limit activation used|:count of :limit activations used', $row['limit'], [ 'count' => $row['activations'], 'limit' => $row['limit'] ] ) }}
                                @endif
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ( $licences->hasPages() )
                <x-artisanpack-pagination :rows="$licences" data-account-licences-pagination />
            @endif
        </x-artisanpack-card>
    @endif
</div>
