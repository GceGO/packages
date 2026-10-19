<?php

declare(strict_types=1);

namespace Gcego\Media;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Media\Access\MediaAccess;
use Gcego\Media\Attachments\MediaAttachments;
use Gcego\Media\Attachments\MediaReplacer;
use Gcego\Media\Collections\MediaCollectionRegistry;
use Gcego\Media\Console\CleanupMediaCommand;
use Gcego\Media\Console\RegenerateMediaCommand;
use Gcego\Media\Diagnostics\MediaInspector;
use Gcego\Media\Downloads\DownloadManager;
use Gcego\Media\Downloads\SignedMediaUrl;
use Gcego\Media\Http\MediaController;
use Gcego\Media\Integration\MediaNotifier;
use Gcego\Media\Integration\PanelMediaExtension;
use Gcego\Media\Library\MediaLibraryQuery;
use Gcego\Media\Livewire\MediaBrowser;
use Gcego\Media\Livewire\MediaLibrary;
use Gcego\Media\Livewire\MediaUploader;
use Gcego\Media\Metadata\MediaMetadataExtractor;
use Gcego\Media\Storage\MediaStorage;
use Gcego\Media\Storage\StoragePathResolver;
use Gcego\Media\Transformations\ImageTransformer;
use Gcego\Media\Transformations\MediaProcessor;
use Gcego\Media\Uploads\FileInspector;
use Gcego\Media\Uploads\TemporaryUploads;
use Gcego\Media\Uploads\UploadManager;
use Gcego\Media\Uploads\UploadValidator;
use Gcego\Panels\PanelManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

use function Livewire\on;

/**
 * Laravel integration for gcego/media; also the "gcego/media" GCEGO package.
 *
 * register(): configuration and services (no queries, no storage access).
 * boot(): views, translations, migrations, the media routes (unless routes
 * are cached), Livewire components (gcego-media-library,
 * gcego-media-browser, gcego-media-uploader), the hook binding Livewire
 * temporary uploads to their uploader, styling slots and icons, the panel
 * extension and the console commands.
 */
final class MediaServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-media';

    /** Styling slots of media views (applied with ChromaManager::override(MediaCollection::class, ...)). */
    public const array Slots = [
        'upload' => 'The upload area.', 'dropzone' => 'The drop zone.', 'upload_button' => 'The file chooser.', 'progress' => 'The progress bar.',
        'preview' => 'A preview.', 'error' => 'Upload errors.', 'grid' => 'The library grid.', 'card' => 'A library card.', 'thumbnail' => 'A thumbnail.',
        'metadata' => 'The details panel.', 'browser' => 'The media browser.', 'toolbar' => 'Filters.', 'selection' => 'Bulk actions.', 'empty' => 'The empty state.',
        'loading' => 'The loading state.', 'actions' => 'Actions.',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-media.php', self::Name);

        $this->app->singleton(MediaConfig::class, static function (Application $app): MediaConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return MediaConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([MediaManager::class, MediaCollectionRegistry::class, StoragePathResolver::class, MediaStorage::class, FileInspector::class, UploadValidator::class, ImageTransformer::class, MediaMetadataExtractor::class, SignedMediaUrl::class, MediaInspector::class, MediaNotifier::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request (or queue job): they read the current user and tenant.
        foreach ([MediaAccess::class, TemporaryUploads::class, UploadManager::class, MediaAttachments::class, MediaReplacer::class, DownloadManager::class, MediaProcessor::class, MediaLibraryQuery::class] as $scoped) {
            $this->app->scoped($scoped);
        }
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-media.php' => $this->app->configPath('gcego-media.php')], 'gcego-media-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-media-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-media')], 'gcego-media-views');

        Livewire::component('gcego-media-library', MediaLibrary::class);
        Livewire::component('gcego-media-browser', MediaBrowser::class);
        Livewire::component('gcego-media-uploader', MediaUploader::class);

        // Bind each finished Livewire upload to the user who uploaded it (in their own request).
        on('call', function (mixed $component, string $method, array $params): void {
            if ($method !== '_finishUpload') {
                return;
            }

            $filenames = [];

            $signedPaths = $params[1] ?? [];

            foreach (is_iterable($signedPaths) ? $signedPaths : [$signedPaths] as $signed) {
                $path = is_string($signed) ? TemporaryUploadedFile::extractPathFromSignedPath($signed) : false;

                if (is_string($path)) {
                    $filenames[] = basename($path);
                }
            }

            $this->app->make(TemporaryUploads::class)->register($filenames);
        });

        $this->registerRoutes();
        $this->defineSlots();

        if ($this->app->bound(PanelManager::class)) {
            $this->app->make(PanelManager::class)->extend(PanelMediaExtension::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CleanupMediaCommand::class, RegenerateMediaCommand::class]);
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/media',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/forms', 'gcego/tables', 'gcego/infolists', 'gcego/panels', 'gcego/notifications'],
            capabilities: ['media', 'media.uploads', 'media.attachments', 'media.variants', 'media.downloads', 'media.library', 'media.scanning'],
            description: 'Secure uploads, collections, attachments, private downloads, image variants and a media library.',
        );
    }

    private function registerRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(function (): void {
            $config = $this->app->make(MediaConfig::class);
            $router = $this->app->make(Router::class);
            $pattern = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

            $router->middleware($config->downloadMiddleware)->prefix($config->routePrefix)->group(static function (Router $router) use ($pattern): void {
                $router->get('{media}', [MediaController::class, 'show'])->where('media', $pattern)->name('gcego.media.show');
                $router->get('{media}/download', [MediaController::class, 'download'])->where('media', $pattern)->name('gcego.media.download');
                $router->get('{media}/variants/{variant}', [MediaController::class, 'variant'])->where(['media' => $pattern, 'variant' => '[a-z][a-z0-9_-]{0,31}'])->name('gcego.media.variant');
            });
            $router->getRoutes()->refreshNameLookups();
        });
    }

    private function defineSlots(): void
    {
        $slots = [];

        foreach (self::Slots as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(MediaCollection::class, $slots, inherit: true);
    }
}
