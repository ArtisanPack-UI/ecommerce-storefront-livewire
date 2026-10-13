<!DOCTYPE html>
<html>
<head><title>@yield( 'title' ) | Host</title></head>
<body>
    <header data-host-header>Host header</header>
    <main>@yield( 'content' )</main>
    @include( 'ecommerce-storefront::partials.global' )
    @livewireScripts
</body>
</html>
