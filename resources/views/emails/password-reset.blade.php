@extends('emails.layout')

@section('content')
    <div class="email-greeting">
        {{ trans('auth.emails.password_reset.greeting', ['name' => $user->name]) }}
    </div>
    
    <div class="email-content">
        <p>{{ trans('auth.emails.password_reset.intro') }}</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" class="email-button">
                {{ trans('auth.emails.password_reset.button_text') }}
            </a>
        </div>
        
        <p style="font-size: 14px; color: #6b7280; border: 1px solid #e5e7eb; padding: 15px; border-radius: 6px; background-color: #f9fafb;">
            <strong>{{ trans('auth.emails.password_reset.security_note', [], 'Security Note:') }}</strong><br>
            {{ trans('auth.emails.password_reset.outro', ['count' => $expireMinutes]) }}
        </p>
        
        <p style="margin-top: 20px; font-size: 14px; color: #6b7280;">
            {{ trans('auth.emails.password_reset.manual_copy', [], 'If the button above doesn\'t work, copy and paste this link into your browser:') }}<br>
            <a href="{{ $resetUrl }}" style="color: #667eea; word-break: break-all;">{{ $resetUrl }}</a>
        </p>
        
        <p style="margin-top: 30px; font-size: 14px; color: #ef4444;">
            {{ trans('auth.emails.password_reset.ignore_note', [], 'If you did not request a password reset, please ignore this email or contact support if you have concerns.') }}
        </p>
    </div>
@endsection
