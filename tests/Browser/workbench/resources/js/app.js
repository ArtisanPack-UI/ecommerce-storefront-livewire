/*
 * The script the storefront expects a host to load: the design system's
 * behavior engine. Alpine comes with Livewire, so the engine registers as
 * Alpine starts.
 */
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
