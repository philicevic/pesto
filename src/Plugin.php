<?php

declare(strict_types=1);

namespace Philicevic\Pesto;

use Pest\Contracts\Plugins\Bootable;
use Philicevic\Pesto\Expectations\Typo3Expectations;

/**
 * The main Pest plugin class for Pesto.
 *
 * Automatically discovered by Pest via composer.json's extra.pest.plugins.
 * Registers all TYPO3-specific expectations when Pest boots.
 */
final class Plugin implements Bootable
{
    public function boot(): void
    {
        Typo3Expectations::register();
    }
}
