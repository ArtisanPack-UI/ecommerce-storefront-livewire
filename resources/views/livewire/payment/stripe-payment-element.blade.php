{{--
    The Stripe Payment Element driver (#112). See StripePaymentElement.

    A Card holding Stripe's form on the default surface, then the
    reassurance line (a shield icon and "Your card details go straight to
    Stripe…") and a full-width "Pay now". While Stripe loads, field
    skeletons (one full-width, then two side by side) sit under the design
    system's glass Loading overlay with "Loading the payment form…". A
    Stripe error shows under the form, in the error text color, in an
    assertive live region. Without a publishable key and client secret, a
    warning Alert says the method isn't available.

    The form sits in `wire:ignore` so Livewire never re-renders Stripe's
    iframe; the script below runs once per mount. Stripe's `appearance` is
    built from the `--apui-*` tokens so its fields match the Input.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-stripe" data-payment-driver="stripe-payment-element">
    @if ( $available )
        <x-artisanpack-card class="ec-stripe__card">
            <div wire:ignore class="ec-stripe__form">
                <div class="ec-stripe__field" data-loading>
                    <div class="ec-stripe__loading" data-stripe-loading>
                        <div class="ec-stripe__skeletons" aria-hidden="true">
                            <x-artisanpack-skeleton class="ec-stripe__skeleton" />
                            <div class="ec-stripe__skeleton-row">
                                <x-artisanpack-skeleton class="ec-stripe__skeleton" />
                                <x-artisanpack-skeleton class="ec-stripe__skeleton" />
                            </div>
                        </div>

                        <x-artisanpack-loading overlay :delay="0">{{ __( 'Loading the payment form…' ) }}</x-artisanpack-loading>
                    </div>

                    <div class="ec-stripe__element" data-stripe-element></div>
                </div>

                <p class="ec-stripe__error" role="alert" aria-live="assertive" data-stripe-error></p>

                <p class="ec-stripe__reassurance">
                    <x-artisanpack-icon name="shield" />
                    <span>{{ __( 'Your card details go straight to Stripe. This store never sees or stores them.' ) }}</span>
                </p>

                <x-artisanpack-button type="button" color="primary" leading-icon="lock" class="ec-stripe__pay" disabled data-stripe-pay>{{ __( 'Pay now' ) }}</x-artisanpack-button>
            </div>
        </x-artisanpack-card>
    @else
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'This payment method isn\'t available.' )" data-payment-unavailable />
    @endif
</div>

@if ( $available )
    @script
    <script>
        const root       = $wire.$el;
        const settings   = @js( $stripe );
        const loading    = root.querySelector( '[data-stripe-loading]' );
        const field      = root.querySelector( '.ec-stripe__field' );
        const mountPoint = root.querySelector( '[data-stripe-element]' );
        const errorBox   = root.querySelector( '[data-stripe-error]' );
        const payButton  = root.querySelector( '[data-stripe-pay]' );

        let stripe   = null;
        let elements = null;
        let busy     = false;

        // Clears first so the same message is announced again.
        const showError = ( message ) => {
            errorBox.textContent = '';
            window.requestAnimationFrame( () => {
                errorBox.textContent = message || settings.messages.failed;
            } );
        };

        // Stripe.js is loaded once per page, from Stripe's CDN (PCI).
        const loadStripe = () => {
            if ( window.Stripe ) {
                return Promise.resolve( window.Stripe );
            }

            window.ecommerceStripeJs ??= new Promise( ( resolve, reject ) => {
                const script = document.createElement( 'script' );

                script.src   = settings.src;
                script.async = true;

                script.onload = () => window.Stripe ? resolve( window.Stripe ) : reject( new Error( 'Stripe.js did not load.' ) );
                script.onerror = () => {
                    window.ecommerceStripeJs = null;
                    script.remove();
                    reject( new Error( 'Stripe.js did not load.' ) );
                };

                document.head.appendChild( script );
            } );

            return window.ecommerceStripeJs;
        };

        // Design-system colours may be oklch(), which Stripe can't read:
        // paint one pixel and read it back as rgb().
        const toRgb = ( value ) => {
            const context = document.createElement( 'canvas' ).getContext( '2d', { willReadFrequently: true } );

            if ( ! value || ! context ) {
                return null;
            }

            context.clearRect( 0, 0, 1, 1 );
            context.fillStyle = value;
            context.fillRect( 0, 0, 1, 1 );

            const [ red, green, blue ] = context.getImageData( 0, 0, 1, 1 ).data;

            return `rgb(${ red }, ${ green }, ${ blue })`;
        };

        // Stripe's appearance from the --apui-* tokens, so its fields match
        // the Input: the field radius, border, text, placeholder and error
        // colours, and the focus ring. The gateway's `appearance` config
        // goes on top.
        const appearance = () => {
            const styles = getComputedStyle( root );
            const token  = ( name ) => styles.getPropertyValue( name ).trim();
            const colour = ( name ) => toRgb( token( name ) );

            const variables = Object.fromEntries( Object.entries( {
                colorPrimary: colour( '--apui-color-primary' ),
                colorBackground: colour( '--apui-surface-default' ),
                colorText: colour( '--apui-text-default' ),
                colorTextSecondary: colour( '--apui-text-muted' ),
                colorTextPlaceholder: colour( '--apui-text-subtle' ),
                colorDanger: colour( '--apui-color-error-text' ),
                borderRadius: token( '--apui-radius-field' ) || null,
                fontFamily: styles.fontFamily || null,
            } ).filter( ( [ , value ] ) => null !== value && '' !== value ) );

            // Stripe only takes plain values in `rules` (no calc(), nothing
            // empty), so the ring's spread is worked out here in px and
            // unset declarations are dropped.
            const px         = ( name, fallback ) => Number.parseFloat( token( name ) ) || fallback;
            const border     = colour( '--apui-border-default' );
            const hover      = colour( '--apui-border-strong' );
            const ring       = colour( '--apui-focus-ring-color' );
            const ringOffset = colour( '--apui-focus-ring-offset-color' ) || variables.colorBackground;
            const offset     = px( '--apui-focus-ring-offset', 2 );
            const spread     = offset + px( '--apui-focus-ring-width', 2 );
            const declare    = ( declarations ) => Object.fromEntries( Object.entries( declarations ).filter( ( [ , value ] ) => null !== value && undefined !== value && '' !== value ) );
            const rules      = Object.fromEntries( Object.entries( {
                '.Input': declare( {
                    border: border ? `${ px( '--apui-border-width', 1 ) }px solid ${ border }` : null,
                    boxShadow: 'none',
                } ),
                '.Input:hover': declare( { borderColor: hover } ),
                '.Input:focus': declare( {
                    outline: 'none',
                    boxShadow: ring ? `0 0 0 ${ offset }px ${ ringOffset }, 0 0 0 ${ spread }px ${ ring }` : null,
                } ),
                '.Label': declare( { color: variables.colorText, fontWeight: token( '--apui-font-weight-medium' ) } ),
                '.Error': declare( { color: variables.colorDanger } ),
            } ).filter( ( [ , declarations ] ) => Object.keys( declarations ).length > 0 ) );

            const custom = settings.appearance || {};

            return {
                theme: 'stripe',
                ...custom,
                variables: { ...variables, ...( custom.variables || {} ) },
                rules: { ...rules, ...( custom.rules || {} ) },
            };
        };

        // The form is ready (or failed): drop the skeletons and the overlay.
        const doneLoading = () => {
            loading.hidden = true;
            field.removeAttribute( 'data-loading' );
        };

        $wire.on( 'payment-failed', ( event ) => showError( event?.message ) );

        loadStripe()
            .then( ( Stripe ) => {
                stripe   = Stripe( settings.publishableKey, { locale: settings.locale } );
                elements = stripe.elements( { clientSecret: settings.clientSecret, appearance: appearance(), locale: settings.locale } );

                const element = elements.create( 'payment' );

                element.on( 'ready', () => {
                    doneLoading();
                    payButton.disabled = false;
                } );

                element.on( 'loaderror', ( event ) => {
                    doneLoading();
                    showError( event?.error?.message || settings.messages.loadFailed );
                } );

                element.mount( mountPoint );

                // Light, dark or a theme switch while the form is open: give
                // Stripe the new token values.
                const scheme   = window.matchMedia( '(prefers-color-scheme: dark)' );
                const observer = new MutationObserver( () => retheme() );
                const retheme  = () => {
                    if ( ! root.isConnected ) {
                        observer.disconnect();
                        scheme.removeEventListener( 'change', retheme );

                        return;
                    }

                    elements.update( { appearance: appearance() } );
                };

                scheme.addEventListener( 'change', retheme );
                observer.observe( document.documentElement, { attributes: true, attributeFilter: [ 'class', 'data-theme' ] } );
            } )
            .catch( () => {
                doneLoading();
                showError( settings.messages.loadFailed );
            } );

        payButton.addEventListener( 'click', async () => {
            if ( busy || ! stripe || ! elements ) {
                return;
            }

            busy                 = true;
            payButton.disabled   = true;
            errorBox.textContent = '';

            try {
                const { error, paymentIntent } = await stripe.confirmPayment( {
                    elements,
                    confirmParams: { return_url: settings.returnUrl },
                    redirect: 'if_required',
                } );

                if ( error ) {
                    // Field errors are the shopper's to fix; anything else
                    // (a declined card) is a failed payment.
                    if ( 'validation_error' === error.type ) {
                        showError( error.message );
                    } else {
                        await $wire.fail( error.message || '' );
                    }

                    return;
                }

                await $wire.confirm( paymentIntent?.id ?? null );
            } catch ( exception ) {
                showError( settings.messages.failed );
            } finally {
                busy               = false;
                payButton.disabled = false;
            }
        } );
    </script>
    @endscript
@endif
