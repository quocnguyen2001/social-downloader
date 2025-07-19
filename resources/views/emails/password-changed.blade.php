@extends('emails.layout')

@section('content')
    <div class="email-greeting">
        {{ trans('auth.emails.password_changed.greeting', ['name' => $user->name]) }}
    </div>
    
    <div class="email-content">
        <p>{{ trans('auth.emails.password_changed.intro') }}</p>
        
        <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 6px; margin: 20px 0;">
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <div style="width: 20px; height: 20px; background-color: #22c55e; border-radius: 50%; display: inline-block; margin-right: 10px; position: relative;">
                    <span style="color: white; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">✓</span>
                </div>
                <strong style="color: #166534;">{{ trans('auth.emails.password_changed.success_title', [], 'Password Updated Successfully') }}</strong>
            </div>
            <p style="color: #166534; margin: 0; font-size: 14px;">
                {{ trans('auth.emails.password_changed.success_message', [], 'Your password has been successfully changed on') }} {{ now()->format('F j, Y \a\t g:i A T') }}
            </p>
        </div>
        
        <p>{{ trans('auth.emails.password_changed.security_tips', [], 'For your security, we recommend:') }}</p>
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>{{ trans('auth.emails.password_changed.tip_1', [], 'Keep your password confidential') }}</li>
            <li>{{ trans('auth.emails.password_changed.tip_2', [], 'Use a unique password for this account') }}</li>
            <li>{{ trans('auth.emails.password_changed.tip_3', [], 'Enable two-factor authentication if available') }}</li>
            <li>{{ trans('auth.emails.password_changed.tip_4', [], 'Log out of all devices if you suspect unauthorized access') }}</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $appUrl }}" class="email-button">
                {{ trans('auth.emails.password_changed.button_text', [], 'Access Your Account') }}
            </a>
        </div>
        
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 6px; margin: 20px 0;">
            <p style="color: #dc2626; margin: 0; font-size: 14px;">
                <strong>{{ trans('auth.emails.password_changed.security_alert', [], 'Security Alert:') }}</strong><br>
                {{ trans('auth.emails.password_changed.outro') }}
            </p>
            @if(isset($supportEmail))
            <p style="color: #dc2626; margin: 10px 0 0 0; font-size: 14px;">
                {{ trans('auth.emails.password_changed.contact_support', [], 'Contact support immediately:') }} 
                <a href="mailto:{{ $supportEmail }}" style="color: #dc2626;">{{ $supportEmail }}</a>
            </p>
            @endif
        </div>
    </div>
@endsection
