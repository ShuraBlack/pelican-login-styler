<?php

namespace ShuraBlack\LoginStyler;

use App\Filament\Pages\Auth\Login;
use Filament\Facades\Filament;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;

// Pelican's login page with a configurable title (brand name) and heading.
class StyledLogin extends Login
{
    // Runs on every request of this page (initial load and Livewire updates), so the title never reverts.
    public function booted(): void
    {
        if (filled($title = config('login-styler.title'))) {
            Filament::getCurrentPanel()?->brandName($title);
        }
    }

    #[Unexposed]
    public function getHeading(): string|Htmlable|null
    {
        $heading = config('login-styler.heading');

        // Keep Filament's own heading during the multi-factor step.
        return filled($heading) && blank($this->userUndertakingMultiFactorAuthentication) ? $heading : parent::getHeading();
    }
}
