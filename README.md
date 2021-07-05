# Kirby Vite Plugin

A set of helper functions to get the correct path to your versioned css and js files generated by [Vite](https://github.com/vitejs/vite).

## Use-cases

You can use kirby-vite for a single or multi-page vanilla js setup or as a basis for an SPA setup. If you plan to use `Kirby` together with `Vue 3` also checkout [Johann Schopplich](https://github.com/johannschopplich)'s [Kirby + Vue 3 Starterkit](https://github.com/johannschopplich/kirby-vue3-starterkit)!

## Getting started

The easiest way to get started is using the [basic starter kit](https://github.com/arnoson/kirby-vite-basic-kit) or the [multi-page kit](https://github.com/arnoson/kirby-vite-multi-page-kit).

## Usage

Make sure you have the right [setup](#setup).
Then inside your template files (or anywhere else) you can use the helper functions.

```php
<html>
  <head>
    <?= vite()->client() ?>
    <?= vite()->css() ?>
  </head>
  <body>
    <?= vite()->js() ?>
  </body>
</html>
```

## Setup
If you want use the plugin without one of the starter kits, you can add it to your existing kirby setup.

### Installation

```
composer require arnoson/kirby-vite
```

### Folder structure

Make sure you use a modern [public folder structure](https://getkirby.com/docs/guide/configuration#custom-folder-setup__public-folder-setup).

### Static assets

During development Kirby can't access your static files located in the src folder. Therefore it's necessary to create a symbolic link inside of the public folder:
```
ln -s $PWD/src/assets ./public/assets
```
For more information and an example `vite.config.js` have a look at the [basic starter kit](https://github.com/arnoson/kirby-vite-basic-kit).

## Legacy build
Since version `2.3.0` you can easily support legacy browsers that do not support native ESM.
Therefore add the [@vitejs/plugin-legacy](https://github.com/vitejs/vite/tree/main/packages/plugin-legacy) plugin to your project and enable the legacy option in your `config.php`:
```php
'arnoson.kirby-vite.legacy' => true
```
Now call kirby-vite's `js()` helper as usual and make sure to add the legacy polyfills:

```php
<!-- your template -->
<?= vite()->js() ?>
<?= vite()->legacyPolyfills() ?>
```

which will render:

```html
<script src="http://your-website.org/dist/assets/index.[hash].js" type="module"></script>
<script src="http://your-website.org/dist/assets/index-legacy.[hash].js" nomodule=""></script>
<script src="http://your-website.org/dist/assets/polyfills-legacy.[hash].js" nomodule=""></script>
```

If you want to have more control over where the legacy files are rendered, disable `arnoson.kirby-vite.legacy` and use kirby-vite's legacy helpers manually:

```php
<?= vite()->js() ?>
<?= vite()->legacyJs() ?>
<?= vite()->legacyPolyfills() ?>
```

### Known issue
`@vitejs/plugin-legacy` will inline the css in the legacy js entry. So users with a legacy browser will download the css twice. [See this issue](https://github.com/vitejs/vite/issues/2062).

## Watch php files

If you also want to live reload your site in development mode whenever you change something in kirby, install [vite-plugin-live-reload](https://github.com/arnoson/vite-plugin-live-reload). Then adjust your `vite.config.js`:

```js
// vite.config.js
import liveReload from 'vite-plugin-live-reload';

export default {
  // ...
  plugins: [
    liveReload(
      '../content/**/*',
      '../public/site/(templates|snippets|controllers|models)/**/*.php'
    )
  ]
};
```

## Credits

This plugin is highly inspired by [Diverently](https://github.com/Diverently)'s [Laravel Mix Helper for Kirby](https://github.com/Diverently/laravel-mix-kirby) and [André Felipe](https://github.com/andrefelipe)'s [vite-php-setup](https://github.com/andrefelipe/vite-php-setup). Many of the fine tunings I owe to [Johann Schopplich](https://github.com/johannschopplich) and his [Kirby + Vue 3 Starterkit](https://github.com/johannschopplich/kirby-vue3-starterkit).