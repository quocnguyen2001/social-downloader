{{ trans('auth.emails.welcome.greeting', ['name' => $user->name]) }}

{{ trans('auth.emails.welcome.intro', ['app_name' => $appName]) }}

{{ trans('auth.emails.welcome.features', [], 'With your account, you can:') }}

• {{ trans('auth.emails.welcome.feature_1', [], 'Access our powerful video extraction tools') }}
• {{ trans('auth.emails.welcome.feature_2', [], 'Manage your API tokens and usage') }}
• {{ trans('auth.emails.welcome.feature_3', [], 'Track your download history') }}
• {{ trans('auth.emails.welcome.feature_4', [], 'Get priority support') }}

{{ trans('auth.emails.welcome.button_text') }}: {{ $appUrl }}

{{ trans('auth.emails.welcome.outro') }}

---

{{ trans('auth.emails.welcome.account_details', [], 'Your account details:') }}
{{ trans('auth.emails.welcome.email_label', [], 'Email:') }} {{ $user->email }}
{{ trans('auth.emails.welcome.registered_label', [], 'Registered:') }} {{ $user->created_at->format('F j, Y \a\t g:i A') }}

---

{{ trans('auth.emails.footer', ['app_name' => $appName, 'year' => date('Y')], '© ' . date('Y') . ' ' . $appName . '. All rights reserved.') }}

@if(isset($supportEmail))
{{ trans('auth.emails.support', [], 'Need help?') }} {{ trans('auth.emails.contact_support', [], 'Contact Support') }}: {{ $supportEmail }}
@endif
