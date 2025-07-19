<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName ?? config('app.name') }}</title>
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
        }

        /* Remove blue links for iOS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .email-body {
            padding: 40px 30px;
        }

        .email-greeting {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .email-content {
            margin-bottom: 30px;
            line-height: 1.7;
        }

        .email-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .email-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -1px rgba(0, 0, 0, 0.15);
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .email-footer p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
        }

        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }

        .email-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0 10px;
                border-radius: 0;
            }
            
            .email-header,
            .email-body,
            .email-footer {
                padding: 20px;
            }
            
            .email-header h1 {
                font-size: 24px;
            }
            
            .email-greeting {
                font-size: 18px;
            }
            
            .email-button {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-wrapper {
                background-color: #111827 !important;
            }
            
            .email-container {
                background-color: #1f2937 !important;
            }
            
            .email-body {
                color: #f9fafb !important;
            }
            
            .email-greeting {
                color: #ffffff !important;
            }
            
            .email-footer {
                background-color: #374151 !important;
                border-top-color: #4b5563 !important;
            }
            
            .email-footer p {
                color: #d1d5db !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1>{{ $appName ?? config('app.name') }}</h1>
            </div>
            
            <div class="email-body">
                @yield('content')
            </div>
            
            <div class="email-footer">
                <p>
                    @yield('footer', trans('auth.emails.footer', [
                        'app_name' => $appName ?? config('app.name'),
                        'year' => date('Y')
                    ], '© ' . date('Y') . ' ' . ($appName ?? config('app.name')) . '. All rights reserved.'))
                </p>
                @if(isset($supportEmail))
                <p style="margin-top: 10px;">
                    {{ trans('auth.emails.support', [], 'Need help?') }} 
                    <a href="mailto:{{ $supportEmail }}">{{ trans('auth.emails.contact_support', [], 'Contact Support') }}</a>
                </p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
