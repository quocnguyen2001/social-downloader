<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\Page;

class TokenGenerated extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.token-generated';

    protected static ?string $title = 'API Token Generated';

    protected static bool $shouldRegisterNavigation = false;

    public function getHeading(): string
    {
        return 'API Token Generated';
    }

    public function getSubheading(): ?string
    {
        return 'Your new API token has been generated successfully. Please copy and store it securely.';
    }
}
