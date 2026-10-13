---
title: Visual Editor Overview
---

# Visual Editor

When `artisanpack-ui/visual-editor` is installed, the storefront adds commerce blocks to the editor, and can render its pages through editor templates. The editor is a soft dependency: the storefront works the same without it, and nothing here needs a JavaScript build.

## In this section

- [Blocks](Visual-Editor-Blocks) - Every commerce block, its attributes, and how it finds its product
- [Templates and Patterns](Visual-Editor-Templates) - The default storefront templates, how a page picks one, and the block patterns

## When blocks are registered

The blocks are registered only when all of these hold:

- `visual_editor.blocks` is `true` in `config/artisanpack/ecommerce-storefront-livewire.php` (the default);
- the class `ArtisanPackUI\VisualEditor\VisualEditor` exists;
- that service is bound in the container.

Registration waits until every service provider has booted, so blocks that depend on another satellite (Recently Viewed) see it. Each block is registered with `VisualEditor::registerServerBlock()`: the editor builds the inspector from the block's attributes and previews it through the server.

When blocks are available the storefront also:

- adds its block names to visual-editor's `enabled_blocks` allow-list, when you use one (an empty list already allows every block);
- maps `products` to the engine's `Product` model in visual-editor's resources (`ap.visualEditor.resources`), unless you mapped that key yourself, so a preview can name the product being edited;
- offers its block patterns, and, when `visual_editor.templates` is on, its default templates.

Turn the blocks off with:

```php
// config/artisanpack/ecommerce-storefront-livewire.php
'visual_editor' => [
    'blocks'    => false,
    'templates' => false,
],
```

## Block names

Every block is named `artisanpack-commerce/{slug}`, for example `artisanpack-commerce/product-grid`. They appear in the editor's `widgets` inserter category, with the keywords "commerce", "shop", and "store".

## Templates are opt-in

`visual_editor.templates` is `false` by default, so installing the editor does not change an existing store's pages. `php artisan ecommerce-storefront:install` warns when visual-editor is installed and templates are off. See [Templates and Patterns](Visual-Editor-Templates).
