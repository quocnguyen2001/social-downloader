@extends('emails.layout')

@section('content')
    <div class="email-greeting">
        {{ trans('auth.emails.welcome.greeting', ['name' => $user->name]) }}
    </div>
    
    <div class="email-content">
        <p>{{ trans('auth.emails.welcome.intro', ['app_name' => $appName]) }}</p>
        
        <p>{{ trans('auth.emails.welcome.features', [], 'With your account, you can:') }}</p>
        <ul style="margin: 20px 0; padding-left: 20px;">
            <li>{{ trans('auth.emails.welcome.feature_1', [], 'Access our powerful video extraction tools') }}</li>
            <li>{{ trans('auth.emails.welcome.feature_2', [], 'Manage your API tokens and usage') }}</li>
            <li>{{ trans('auth.emails.welcome.feature_3', [], 'Track your download history') }}</li>
            <li>{{ trans('auth.emails.welcome.feature_4', [], 'Get priority support') }}</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $appUrl }}" class="email-button">
                {{ trans('auth.emails.welcome.button_text') }}
            </a>
        </div>
        
        <p>{{ trans('auth.emails.welcome.outro') }}</p>
        
        <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
            {{ trans('auth.emails.welcome.account_details', [], 'Your account details:') }}<br>
            <strong>{{ trans('auth.emails.welcome.email_label', [], 'Email:') }}</strong> {{ $user->email }}<br>
            <strong>{{ trans('auth.emails.welcome.registered_label', [], 'Registered:') }}</strong> {{ $user->created_at->format('F j, Y \a\t g:i A') }}
        </p>
    </div>
@endsection
