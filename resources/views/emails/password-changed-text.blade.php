{{ trans('auth.emails.password_changed.greeting', ['name' => $user->name]) }}

{{ trans('auth.emails.password_changed.intro') }}

✓ {{ trans('auth.emails.password_changed.success_title', [], 'Password Updated Successfully') }}
{{ trans('auth.emails.password_changed.success_message', [], 'Your password has been successfully changed on') }} {{ now()->format('F j, Y \a\t g:i A T') }}

{{ trans('auth.emails.password_changed.security_tips', [], 'For your security, we recommend:') }}

• {{ trans('auth.emails.password_changed.tip_1', [], 'Keep your password confidential') }}
• {{ trans('auth.emails.password_changed.tip_2', [], 'Use a unique password for this account') }}
• {{ trans('auth.emails.password_changed.tip_3', [], 'Enable two-factor authentication if available') }}
• {{ trans('auth.emails.password_changed.tip_4', [], 'Log out of all devices if you suspect unauthorized access') }}

{{ trans('auth.emails.password_changed.button_text', [], 'Access Your Account') }}: {{ $appUrl }}

⚠️ {{ trans('auth.emails.password_changed.security_alert', [], 'Security Alert:') }}
{{ trans('auth.emails.password_changed.outro') }}

@if(isset($supportEmail))
{{ trans('auth.emails.password_changed.contact_support', [], 'Contact support immediately:') }} {{ $supportEmail }}
@endif

---

{{ trans('auth.emails.footer', ['app_name' => $appName, 'year' => date('Y')], '© ' . date('Y') . ' ' . $appName . '. All rights reserved.') }}

@if(isset($supportEmail))
{{ trans('auth.emails.support', [], 'Need help?') }} {{ trans('auth.emails.contact_support', [], 'Contact Support') }}: {{ $supportEmail }}
@endif
