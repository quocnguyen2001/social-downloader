{{ trans('auth.emails.password_reset.greeting', ['name' => $user->name]) }}

{{ trans('auth.emails.password_reset.intro') }}

{{ trans('auth.emails.password_reset.button_text') }}: {{ $resetUrl }}

{{ trans('auth.emails.password_reset.security_note', [], 'Security Note:') }}
{{ trans('auth.emails.password_reset.outro', ['count' => $expireMinutes]) }}

{{ trans('auth.emails.password_reset.ignore_note', [], 'If you did not request a password reset, please ignore this email or contact support if you have concerns.') }}

---

{{ trans('auth.emails.footer', ['app_name' => $appName, 'year' => date('Y')], '© ' . date('Y') . ' ' . $appName . '. All rights reserved.') }}

@if(isset($supportEmail))
{{ trans('auth.emails.support', [], 'Need help?') }} {{ trans('auth.emails.contact_support', [], 'Contact Support') }}: {{ $supportEmail }}
@endif
