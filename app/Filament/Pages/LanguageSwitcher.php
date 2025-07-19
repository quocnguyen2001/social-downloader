<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Session;

class LanguageSwitcher extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-language';
    
    protected static string $view = 'filament.pages.language-switcher';
    
    protected static ?int $navigationSort = 999;
    
    public static function getNavigationLabel(): string
    {
        return trans('messages.navigation.language');
    }
    
    public function getTitle(): string
    {
        return trans('messages.navigation.language');
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('english')
                ->label('English')
                ->icon('heroicon-o-flag')
                ->color('primary')
                ->action(function () {
                    Session::put('locale', 'en');
                    return redirect()->to(request()->url() . '?locale=en');
                }),
                
            Action::make('vietnamese')
                ->label('Tiếng Việt')
                ->icon('heroicon-o-flag')
                ->color('success')
                ->action(function () {
                    Session::put('locale', 'vi');
                    return redirect()->to(request()->url() . '?locale=vi');
                }),
        ];
    }
}
