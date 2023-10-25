<?php declare(strict_types=1);

namespace arnoson\KirbyVite;
use Kirby\Filesystem\F;
use \Exception;

function getRelativePath(string $rootPath, string $fullPath): ?string {
  $rootPath = realpath(rtrim($rootPath, '/'));
  $fullPath = realpath(rtrim($fullPath, '/'));

  if (strpos($fullPath, $rootPath) === 0) {
    $relativePath = ltrim(substr($fullPath, strlen($rootPath)), DIRECTORY_SEPARATOR);
    return $relativePath;
  }

  return false;
}

class Vite {
  protected static Vite $instance;
  protected bool $isFirstScript = true;
  protected ?string $outDir = null;
  protected ?array $manifest = null;
  protected ?array $config = null;

  public static function getInstance(): Vite {
    return self::$instance ??= new self();
  }

  /**
   * Read `vite.config.php`, generated by vite-plugin-kirby.
   */
  protected function config(): array {
    return $this->config ??= require kirby()->root('config') . '/vite.config.php';
  }

  /**
   * Get Vite's `outDir`, but relative to Kirby's index root. This is important
   * for public folder setups, where Kirby's index is not the project root. In
   * this case (where `public` is the index) `public/dist` would become `dist`.
   */
  protected function outDir(): string {
    return $this->outDir ??= kirby()->root('base')
      ? getRelativePath(
        kirby()->root('index'),
        kirby()->root('base') . '/' . $this->config()['outDir']
      )
      : $this->config()['outDir'];
  }

  /**
   * Check if we're in development mode.
   */
  protected function isDev(): bool {
    $devDir = kirby()->root('base') ?? kirby()->root('index');
    return F::exists("$devDir/.dev");
  }

  /**
   * Read vite's dev server from the `.dev` file.
   *
   * @throws Exception
   */
  protected function server(): string {
    $devDir = kirby()->root('base') ?? kirby()->root('index');
    $dev = F::read("$devDir/.dev");

    [$key, $value] = explode('=', trim($dev), 2);
    if ($key !== 'VITE_SERVER' && option('debug')) {
      throw new Exception('VITE_SERVER not found in `.dev` file.');
    }

    return $value;
  }

  /**
   * Read and parse the manifest file.
   *
   * @throws Exception
   */
  public function manifest(): array {
    if (isset($this->manifest)) {
      return $this->manifest;
    }

    $index = kirby()->root('index');
    $outDir = $this->outDir();
    $manifestPath = "$index/$outDir/manifest.json";

    if (!F::exists($manifestPath)) {
      if (option('debug')) {
        throw new Exception('`manifest.json` not found.');
      }
      return [];
    }

    return $this->manifest = json_decode(F::read($manifestPath), true);
  }

  /**
   * Get the value of a manifest property for a specific entry.
   *
   * @throws Exception
   */
  protected function manifestProperty(string $entry, string $key = 'file', bool $try = false): ?string {
    $manifestEntry = $this->manifest()[$entry] ?? null;
    if (!$manifestEntry) {
      if (!$try && option('debug')) {
        throw new Exception("`$entry` is not a manifest entry.");
      }
      return null;
    }

    $value = $manifestEntry[$key] ?? null;
    if (!$value) {
      if (!$try && option('debug')) {
        throw new Exception("`$key` not found in manifest entry `$entry`");
      }
      return null;
    }

    return $value;
  }

  /**
   * Get the url for the specified file for development mode.
   */
  protected function assetDev(string $file): string {
    return $this->server() . "/$file";
  }

  /**
   * Get the URL for the specified file for production mode.
   */
  protected function assetProd(string $file): string {
    $outDir = $this->outDir();
    return "/$outDir/$file";
  }

  /**
   * Include vite's client in development mode.
   */
  protected function client(): ?string {
    return $this->isDev()
      ? js($this->assetDev('@vite/client'), ['type' => 'module'])
      : null;
  }

  /**
   * Include the js file for the specified entry.
   */
  public function js(string $entry, array $options = [], bool $try = false): ?string {
    $file = $this->file($entry, $try);
    if (!$file && $try) {
      return null;
    }

    $options = array_merge(['type' => 'module'], $options);

    $legacy = $this->config()['legacy'];
    // There might be multiple `vite()->js()` calls but some scripts
    // (vite client, legacy polyfills) should be only included once per page.
    $scripts = [
      $this->isFirstScript ? $this->client() : null,
      $this->isFirstScript && $legacy ? $this->legacyPolyfills() : null,
      $legacy ? $this->legacyJs($entry) : null,
      js($file, $options),
    ];

    $this->isFirstScript = false;
    return implode("\n", array_filter($scripts));
  }

  /**
   * Include the css file for the specified entry in production mode. Your CSS
   * file can either be CSS entry `vite()->css('main.css')` or a js entry
   * `vite()->css('main.js')`, in this case the CSS imported in the JS file will
   *  be used.
   */
  public function css(string $entry, array $options = [], bool $try = false): ?string {
    if ($this->isDev()) {
      return null;
    }

    $extension = F::extension($entry);
    $entryIsStyle =
      $extension === 'css' || $extension === 'scss' || $extension === 'sass';

    $file = null;
    if ($entryIsStyle) {
      $file = $this->manifestProperty($entry, 'file', $try);
    } else {
      $css = $this->manifestProperty($entry, 'css', $try);
      $file = $css ? $css[0] : null;
    }
    if (!$file) {
      return null;
    }

    return css($this->assetProd($file), $options);
  }

  /**
   * Return manifest file path for entry.
   */
  public function file(string $entry, bool $try = false): ?string {
    if ($this->isDev()) {
      return $this->assetDev($entry);
    }

    $property = $this->manifestProperty($entry, 'file', $try);
    return $property ? $this->assetProd($property) : null;
  }

  protected function legacyPolyfills(array $options = []): ?string {
    if ($this->isDev()) {
      return null;
    }

    $entry = null;
    foreach (array_keys($this->manifest()) as $key) {
      // The legacy entry is relative from vite's root folder (e.g.:
      // `../vite/legacy-polyfills-legacy`). To handle all cases we just check
      // for the ending.
      if (str_ends_with($key, 'vite/legacy-polyfills-legacy')) {
        $entry = $key;
        break;
      }
    }

    // Polyfills entry is only generated if any polyfills are used.
    if (!$entry) {
      return null;
    }

    return js($entry, array_merge(['nomodule' => true], $options));
  }

  protected function legacyJs(string $entry, array $options = [], bool $try = false): ?string {
    if ($this->isDev()) {
      return null;
    }

    $parts = explode('.', $entry);
    $parts[count($parts) - 2] .= '-legacy';
    $legacyEntry = join('.', $parts);
    $file = $this->file($legacyEntry, $try);
    if (!$file) {
      return null;
    }

    return js($file, array_merge(['nomodule' => true], $options));
  }
}
