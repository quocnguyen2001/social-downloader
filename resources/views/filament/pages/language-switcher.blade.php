<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                {{ trans('messages.navigation.language') }}
            </h2>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                {{ trans('messages.language_switcher.description') }}
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ request()->url() }}?locale=en" 
                   class="flex items-center p-4 border-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ app()->getLocale() === 'en' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-600' }}">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">🇺🇸</span>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">English</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Default language</p>
                    </div>
                    @if(app()->getLocale() === 'en')
                        <div class="ml-auto">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-primary-500" />
                        </div>
                    @endif
                </a>
                
                <a href="{{ request()->url() }}?locale=vi" 
                   class="flex items-center p-4 border-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ app()->getLocale() === 'vi' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-600' }}">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">🇻🇳</span>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Tiếng Việt</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Vietnamese</p>
                    </div>
                    @if(app()->getLocale() === 'vi')
                        <div class="ml-auto">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-primary-500" />
                        </div>
                    @endif
                </a>
            </div>
        </div>
        
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        {{ trans('messages.language_switcher.note_title') }}
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>{{ trans('messages.language_switcher.note_description') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
