{{--
    The Stripe Payment Element driver. See StripePaymentElement.

    The form sits in `wire:ignore` so Livewire never re-renders Stripe's
    iframe; the script below runs once per mount.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-3" data-payment-driver="stripe-payment-element">
    @if ( $available )
        <div wire:ignore class="flex flex-col gap-3">
            <p class="text-sm text-base-content/70" data-stripe-loading>{{ __( 'Loading the payment form…' ) }}</p>

            <div data-stripe-element></div>

            <p class="text-sm text-error empty:hidden" role="alert" aria-live="assertive" data-stripe-error></p>

            <div>
                <x-artisanpack-button type="button" :label="__( 'Pay now' )" color="primary" icon="o-lock-closed" disabled data-stripe-pay />
            </div>

            <p class="text-xs text-base-content/70">{{ __( 'Your card details go straight to Stripe. This store never sees or stores them.' ) }}</p>
        </div>
    @else
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'This payment method isn\'t available.' )" data-payment-unavailable />
    @endif
</div>

@if ( $available )
    @script
    <script>
        const root       = $wire.$el;
        const settings   = @js( $stripe );
        const loading    = root.querySelector( '[data-stripe-loading]' );
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

        // daisyUI colours may be oklch(), which Stripe can't read: paint
        // one pixel and read it back as rgb().
        const toRgb = ( value ) => {
            const context = document.createElement( 'canvas' ).getContext( '2d' );

            if ( ! value || ! context ) {
                return null;
            }

            context.fillStyle = value;
            context.fillRect( 0, 0, 1, 1 );

            const [ red, green, blue ] = context.getImageData( 0, 0, 1, 1 ).data;

            return `rgb(${ red }, ${ green }, ${ blue })`;
        };

        const appearance = () => {
            const styles    = getComputedStyle( root );
            const token     = ( name ) => styles.getPropertyValue( name ).trim();
            const variables = {};
            const colours   = {
                colorPrimary: '--color-primary',
                colorBackground: '--color-base-100',
                colorText: '--color-base-content',
                colorDanger: '--color-error',
            };

            for ( const [ key, name ] of Object.entries( colours ) ) {
                const colour = toRgb( token( name ) );

                if ( colour ) {
                    variables[ key ] = colour;
                }
            }

            if ( token( '--radius-field' ) ) {
                variables.borderRadius = token( '--radius-field' );
            }

            if ( styles.fontFamily ) {
                variables.fontFamily = styles.fontFamily;
            }

            const custom = settings.appearance || {};

            return { theme: 'stripe', ...custom, variables: { ...variables, ...( custom.variables || {} ) } };
        };

        $wire.on( 'payment-failed', ( event ) => showError( event?.message ) );

        loadStripe()
            .then( ( Stripe ) => {
                stripe   = Stripe( settings.publishableKey, { locale: settings.locale } );
                elements = stripe.elements( { clientSecret: settings.clientSecret, appearance: appearance(), locale: settings.locale } );

                const element = elements.create( 'payment' );

                element.on( 'ready', () => {
                    loading.hidden     = true;
                    payButton.disabled = false;
                } );

                element.on( 'loaderror', ( event ) => {
                    loading.hidden = true;
                    showError( event?.error?.message || settings.messages.loadFailed );
                } );

                element.mount( mountPoint );
            } )
            .catch( () => {
                loading.hidden = true;
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
