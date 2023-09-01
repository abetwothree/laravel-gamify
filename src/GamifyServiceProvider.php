<?php

namespace QCod\Gamify;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use QCod\Gamify\Listeners\SyncBadges;
use Illuminate\Support\ServiceProvider;
use QCod\Gamify\Console\MakeBadgeCommand;
use QCod\Gamify\Console\MakePointCommand;
use QCod\Gamify\Events\ReputationChanged;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RecursiveRegexIterator;
use \RegexIterator;

class GamifyServiceProvider extends PackageServiceProvider
{

    public function configurePackage(Package $package): void
    {
        $package->name('gamify')
            ->hasConfigFile()
            ->hasMigrations([
                'add_reputation_on_user_table',
                'create_gamify_tables',
            ])
            ->hasCommands([
                MakePointCommand::class,
                MakeBadgeCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        Event::listen(ReputationChanged::class, SyncBadges::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('badges', function () {
            return cache()->rememberForever('gamify.badges.all', function () {
                return $this->getBadges()->map(fn($badge) => new $badge);
            });
        });
    }

    /**
     * Get all the badge inside app/Gamify/Badges folder
     *
     * @return Collection<int, class-string>
     */
    protected function getBadges(): Collection
    {
        $badgeRootNamespace = config(
            'gamify.badge_namespace',
            $this->app->getNamespace() . 'Gamify\Badges'
        );

        $badges = [];

        // Get the first folder for the app. For the vast majority of all projects this is "App"
        $rootFolder = substr($badgeRootNamespace, 0, strpos($badgeRootNamespace, '\\'));

        // Create recursive searching classes
        $directory  = new RecursiveDirectoryIterator(app_path('Gamify/Badges/')); 
        $iterator   = new RecursiveIteratorIterator($directory);
        $files      = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH); 

        // loop through each file found
        foreach ($files as $file) { 

            // grab the directory for the file
            $fileDirectory =  pathinfo($file[0], PATHINFO_DIRNAME); 
            
            //remove full server path and prepend the rootfolder 
            $fileDirectory = $rootFolder.str_ireplace(app_path(), '', $fileDirectory);

            // convert the forward slashes to backslashes
            $fileDirectory = str_ireplace('/', '\\', $fileDirectory);

            // get the file name
            $fileName = pathinfo($file[0], PATHINFO_FILENAME); 

            //append namespace file path to the badges array to return
            $badges[] = $fileDirectory."\\".$fileName;
        }

        return collect($badges);
    }
}
