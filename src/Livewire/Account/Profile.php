<?php

/**
 * Account profile and notification preferences component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerNotificationPreference;
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\NotificationPreferenceService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SummarizesErrors;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-profile />`
 *
 * The shopper's profile (spec §7.5, S29): first and last name, phone, and
 * marketing consent, saved through the engine's
 * `CustomerService::updateProfile()` (which records `accepts_marketing_at`
 * when consent is given), and their notification preferences per channel
 * through `NotificationPreferenceService`. Order and account emails
 * (`transactional`) are always sent, so that row is shown switched on and
 * locked.
 *
 * Marketing on any channel needs the shopper's consent: without it the
 * marketing switches are off and locked, and withdrawing consent switches
 * marketing off on every channel, so no stored preference outlives it.
 *
 * Email and password belong to the host's auth (D6): they're shown with
 * links to the host's screens (`auth.profile_route`,
 * `auth.password_route`) when those exist. A signed-in user without a
 * customer record gets one on their first save (once a `MustVerifyEmail`
 * user has verified). Saves count against `ecommerce.cart.mutate`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Profile extends Component
{
    use RateLimitsStorefront;
    use SendsToasts;
    use SummarizesErrors;

    /**
     * The engine rate-limit policy profile and preference saves count
     * against, shared with the account's address writes.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const RATE_LIMIT_POLICY = 'ecommerce.cart.mutate';

    /**
     * The profile fields: `first_name`, `last_name`, `phone`, and
     * `accepts_marketing`.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $profile = [
        'first_name'        => '',
        'last_name'         => '',
        'phone'             => '',
        'accepts_marketing' => false,
    ];

    /**
     * Notification preferences, `channel => [ category => enabled ]`, for
     * the categories the shopper can change.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, bool>>
     */
    public array $preferences = [];

    /**
     * Loads the shopper's profile and preferences.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $customer = Customer::forUser( auth()->user() );

        if ( null !== $customer ) {
            $this->profile = [
                'first_name'        => (string) ( $customer->first_name ?? '' ),
                'last_name'         => (string) ( $customer->last_name ?? '' ),
                'phone'             => (string) ( $customer->phone ?? '' ),
                'accepts_marketing' => (bool) $customer->accepts_marketing,
            ];
        }

        $this->preferences = $this->loadPreferences( $customer );
    }

    /**
     * Saves the name, phone, and marketing consent.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveProfile(): void
    {
        $this->newErrorSummary();
        $this->resetErrorBag();

        $validated = Validator::make( [ 'profile' => $this->profile ], [
            'profile.first_name'        => [ 'nullable', 'string', 'max:255' ],
            'profile.last_name'         => [ 'nullable', 'string', 'max:255' ],
            'profile.phone'             => [ 'nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s]*$/' ],
            'profile.accepts_marketing' => [ 'boolean' ],
        ], [
            'profile.first_name.max' => __( 'Keep this under :max characters.' ),
            'profile.last_name.max'  => __( 'Keep this under :max characters.' ),
            'profile.phone.max'      => __( 'Keep this under :max characters.' ),
            'profile.phone.regex'    => __( 'Use only numbers, spaces, and + ( ) - .' ),
        ] );

        if ( $validated->fails() ) {
            foreach ( $validated->errors()->messages() as $field => $messages ) {
                $this->addError( $field, (string) $messages[0] );
            }

            return;
        }

        $customer = $this->customer();

        if ( null === $customer ) {
            return;
        }

        $updated = $this->rateLimited( self::RATE_LIMIT_POLICY, fn (): ?Customer => $this->writeProfile( $customer ), $this->rateLimitSubject() );

        if ( ! $updated instanceof Customer ) {
            return;
        }

        $withdrawn = true;

        if ( ! (bool) $updated->accepts_marketing ) {
            try {
                $this->withdrawMarketing( $updated );
            } catch ( Throwable $exception ) {
                report( $exception );

                $withdrawn = false;
            }
        }

        $this->profile['accepts_marketing'] = (bool) $updated->accepts_marketing;
        $this->preferences                  = $this->loadPreferences( $updated );

        if ( ! $withdrawn ) {
            $this->toastWarning( __( 'Profile saved, but news and offers may still be on' ), __( 'We couldn\'t switch them off on every channel. Save your details again to retry.' ) );

            return;
        }

        $this->toastSuccess( __( 'Profile saved' ) );
    }

    /**
     * Saves the notification preferences. Unknown channels and categories,
     * and the always-on transactional category, are ignored.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function savePreferences(): void
    {
        $customer = $this->customer();

        if ( null === $customer ) {
            return;
        }

        $service = app( NotificationPreferenceService::class );
        $changes = [];

        foreach ( $service->channels() as $channel ) {
            foreach ( $this->categories() as $category ) {
                $enabled = true === filter_var( $this->preferences[ $channel ][ $category ] ?? false, FILTER_VALIDATE_BOOLEAN );

                $changes[] = [
                    'channel'    => (string) $channel,
                    'category'   => $category,
                    'is_enabled' => 'marketing' === $category ? $enabled && (bool) $customer->accepts_marketing : $enabled,
                ];
            }
        }

        $saved = $this->rateLimited( self::RATE_LIMIT_POLICY, function () use ( $service, $customer, $changes ): bool {
            try {
                $service->update( $customer, $changes );
            } catch ( Throwable $exception ) {
                report( $exception );

                $this->toastError( __( 'We couldn\'t save your notification preferences' ), __( 'Try again in a moment.' ) );

                return false;
            }

            return true;
        }, $this->rateLimitSubject() );

        if ( true !== $saved ) {
            return;
        }

        $this->preferences = $this->loadPreferences( $customer );

        $this->toastSuccess( __( 'Notification preferences saved' ) );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user = auth()->user();

        return view( 'ecommerce-storefront::livewire.account.profile', [
            'fieldIds'    => $this->fieldIds(),
            'email'       => is_string( $user?->email ?? null ) ? $user->email : null,
            'consented'   => (bool) Customer::forUser( $user )?->accepts_marketing,
            'channels'    => $this->channelLabels(),
            'categories'  => $this->categoryLabels(),
            'profileUrl'  => $this->routeUrl( (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.profile_route', '' ) ),
            'passwordUrl' => $this->routeUrl( (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.password_route', '' ) ),
        ] );
    }

    /**
     * The shopper's customer record, made (or linked) when they have none.
     * Null, with a toast, when the engine won't make one (an unverified
     * email another customer already uses), or when a `MustVerifyEmail`
     * user hasn't verified yet: nobody gets a record under an address they
     * haven't proved is theirs.
     *
     * @since 1.0.0
     *
     * @return Customer|null
     */
    protected function customer(): ?Customer
    {
        $user     = auth()->user();
        $customer = null;

        try {
            // An unverified email may belong to someone else: don't make a
            // customer record under it (the engine never moves one to a new email).
            $create   = ! ( $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() );
            $customer = null === $user ? null : app( CustomerService::class )->customerForUser( $user, $create );
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        if ( null === $customer ) {
            $this->toastError( __( 'We couldn\'t save your changes' ), __( 'Your account isn\'t ready yet. Verify your email address and try again.' ) );
        }

        return $customer;
    }

    /**
     * Saves the profile fields through the engine. Null, with a toast, when
     * the engine fails.
     *
     * @since 2.0.0
     *
     * @param  Customer  $customer  The shopper's customer record.
     *
     * @return Customer|null The updated record.
     */
    protected function writeProfile( Customer $customer ): ?Customer
    {
        try {
            return app( CustomerService::class )->updateProfile( $customer, [
                'first_name'        => $this->profile['first_name'],
                'last_name'         => $this->profile['last_name'],
                'phone'             => $this->profile['phone'],
                'accepts_marketing' => (bool) $this->profile['accepts_marketing'],
            ] );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->toastError( __( 'We couldn\'t save your profile' ), __( 'Try again in a moment.' ) );

            return null;
        }
    }

    /**
     * Who a profile write counts against: the signed-in shopper and their IP.
     *
     * @since 2.0.0
     *
     * @return RateLimitSubject
     */
    protected function rateLimitSubject(): RateLimitSubject
    {
        $ip   = request()->ip() ?? '127.0.0.1';
        $user = auth()->user();

        return null === $user ? RateLimitSubject::ip( $ip ) : RateLimitSubject::customer( $user, $ip );
    }

    /**
     * Switches marketing off on every channel, after consent is withdrawn.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The shopper's customer record.
     *
     * @return void
     */
    protected function withdrawMarketing( Customer $customer ): void
    {
        $service = app( NotificationPreferenceService::class );

        $service->update( $customer, array_map(
            static fn ( string $channel ): array => [ 'channel' => $channel, 'category' => 'marketing', 'is_enabled' => false ],
            array_map( 'strval', $service->channels() ),
        ) );
    }

    /**
     * The categories the shopper can switch on and off.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function categories(): array
    {
        return array_values( array_filter(
            CustomerNotificationPreference::CATEGORIES,
            static fn ( string $category ): bool => CustomerNotificationPreference::CATEGORY_TRANSACTIONAL !== $category,
        ) );
    }

    /**
     * Each channel's switches, from the engine's effective values (a
     * category with no stored choice is on, and marketing follows consent).
     * Without a customer record, the defaults.
     *
     * @since 1.0.0
     *
     * @param  Customer|null  $customer  The shopper's customer record.
     *
     * @return array<string, array<string, bool>>
     */
    protected function loadPreferences( ?Customer $customer ): array
    {
        $service     = app( NotificationPreferenceService::class );
        $preferences = [];

        foreach ( $service->channels() as $channel ) {
            foreach ( $this->categories() as $category ) {
                $preferences[ (string) $channel ][ $category ] = 'marketing' === $category ? (bool) $this->profile['accepts_marketing'] : true;
            }
        }

        if ( null === $customer ) {
            return $preferences;
        }

        try {
            foreach ( $service->all( $customer ) as $row ) {
                if ( isset( $preferences[ $row['channel'] ] ) && array_key_exists( $row['category'], $preferences[ $row['channel'] ] ) ) {
                    $preferences[ $row['channel'] ][ $row['category'] ] = (bool) $row['is_enabled'] && ( 'marketing' !== $row['category'] || (bool) $customer->accepts_marketing );
                }
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        return $preferences;
    }

    /**
     * The channels, labelled.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function channelLabels(): array
    {
        $labels = [];

        foreach ( app( NotificationPreferenceService::class )->channels() as $channel ) {
            $labels[ (string) $channel ] = match ( (string) $channel ) {
                'mail'     => __( 'Email' ),
                'sms'      => __( 'Text message' ),
                'database' => __( 'In your account' ),
                default    => Str::headline( (string) $channel ),
            };
        }

        return $labels;
    }

    /**
     * Every category, labelled and described, transactional first.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string|null, locked: bool}>
     */
    protected function categoryLabels(): array
    {
        $labels = [];

        foreach ( CustomerNotificationPreference::CATEGORIES as $category ) {
            $labels[ $category ] = match ( $category ) {
                CustomerNotificationPreference::CATEGORY_TRANSACTIONAL => [ 'label' => __( 'Orders and account' ), 'description' => __( 'Receipts, payment problems, and account security. Always sent.' ), 'locked' => true ],
                'shipping-updates'                                     => [ 'label' => __( 'Shipping updates' ), 'description' => __( 'When your order ships and is delivered.' ), 'locked' => false ],
                'review-requests'                                      => [ 'label' => __( 'Review requests' ), 'description' => __( 'Asking what you thought of things you bought.' ), 'locked' => false ],
                'marketing'                                            => [ 'label' => __( 'News and offers' ), 'description' => __( 'Sales, new products, and other promotions.' ), 'locked' => false ],
                'abandoned-cart'                                       => [ 'label' => __( 'Cart reminders' ), 'description' => __( 'When you leave items in your cart.' ), 'locked' => false ],
                'back-in-stock'                                        => [ 'label' => __( 'Back in stock' ), 'description' => __( 'When something you asked about is available again.' ), 'locked' => false ],
                default                                                => [ 'label' => Str::headline( $category ), 'description' => null, 'locked' => false ],
            };
        }

        return $labels;
    }

    /**
     * A named route's URL, when the route exists.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Route name.
     *
     * @return string|null
     */
    protected function routeUrl( string $name ): ?string
    {
        return '' !== $name && Route::has( $name ) ? route( $name ) : null;
    }

    /**
     * The form's field ids, by error key: prefixed and unique to this
     * component instance, so they can't collide with the host page's own
     * fields (or a second copy of this form), and never dotted.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    protected function fieldIds(): array
    {
        $base = 'ec-account-profile-' . preg_replace( '/[^A-Za-z0-9_-]+/', '', (string) $this->getId() ) . '-';

        return [
            'profile.first_name'        => $base . 'first-name',
            'profile.last_name'         => $base . 'last-name',
            'profile.phone'             => $base . 'phone',
            'profile.accepts_marketing' => $base . 'marketing',
        ];
    }
}
