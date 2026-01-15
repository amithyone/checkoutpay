<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - {{ $appName }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f7fa;
            line-height: 1.6;
            color: #333333;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        .email-header .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 400;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
        }
        .content-text {
            font-size: 15px;
            color: #4a5568;
            margin-bottom: 20px;
            line-height: 1.7;
        }
        .verification-box {
            background: linear-gradient(135deg, #f6f8fb 0%, #e9ecef 100%);
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        .verification-box .icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #ffffff;
        }
        .verification-box .business-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        .verification-box .business-email {
            font-size: 14px;
            color: #718096;
            margin-bottom: 20px;
        }
        .verify-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            margin: 20px 0;
        }
        .verify-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
        }
        .security-note {
            background-color: #fff5f5;
            border-left: 4px solid #fc8181;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .security-note .security-title {
            font-weight: 600;
            color: #c53030;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        .security-note .security-title::before {
            content: "🔒";
            margin-right: 8px;
            font-size: 16px;
        }
        .security-note .security-text {
            font-size: 13px;
            color: #742a2a;
            line-height: 1.6;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 30px 0;
        }
        .feature-item {
            background-color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .feature-item .feature-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .feature-item .feature-text {
            font-size: 12px;
            color: #4a5568;
            font-weight: 500;
        }
        .expiry-notice {
            background-color: #fffaf0;
            border-left: 4px solid #f6ad55;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .expiry-notice .expiry-text {
            font-size: 13px;
            color: #744210;
            line-height: 1.6;
        }
        .email-footer {
            background-color: #1a202c;
            padding: 30px;
            text-align: center;
            border-radius: 0 0 8px 8px;
        }
        .email-footer .footer-text {
            color: #a0aec0;
            font-size: 13px;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .email-footer .footer-links {
            margin-top: 20px;
        }
        .email-footer .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            font-size: 12px;
        }
        .email-footer .footer-links a:hover {
            text-decoration: underline;
        }
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 30px 0;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 30px 20px;
            }
            .email-header {
                padding: 30px 20px;
            }
            .email-header h1 {
                font-size: 24px;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .verification-box {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                @php
                    $emailLogo = \App\Models\Setting::get('email_logo');
                    $emailLogoPath = $emailLogo ? storage_path('app/public/' . $emailLogo) : null;
                @endphp
                @if($emailLogo && $emailLogoPath && file_exists($emailLogoPath))
                    <img src="{{ asset('storage/' . $emailLogo) }}" alt="{{ $appName }}" style="max-height: 50px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">
                @else
                    <h1>{{ $appName }}</h1>
                @endif
                <div class="tagline">Secure Payment Gateway</div>
            </div>

            <!-- Body -->
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                
                <div class="content-text">
                    Welcome to {{ $appName }}! We're excited to have you on board. To get started and ensure the security of your account, please verify your email address.
                </div>

                <!-- Verification Box -->
                <div class="verification-box">
                    <div class="icon">✓</div>
                    <div class="business-name">{{ $business->name }}</div>
                    <div class="business-email">{{ $business->email }}</div>
                    <a href="{{ $verificationUrl }}" class="verify-button">
                        Verify Email Address
                    </a>
                </div>

                <div class="content-text" style="text-align: center; font-size: 13px; color: #718096;">
                    Or copy and paste this link into your browser:<br>
                    <span style="word-break: break-all; color: #667eea;">{{ $verificationUrl }}</span>
                </div>

                <!-- Security Note -->
                <div class="security-note">
                    <div class="security-title">Security Notice</div>
                    <div class="security-text">
                        This verification link will expire in 60 minutes. If you didn't create an account with {{ $appName }}, please ignore this email or contact our support team.
                    </div>
                </div>

                <!-- Features Grid -->
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">💳</div>
                        <div class="feature-text">Secure Payments</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔐</div>
                        <div class="feature-text">Bank-Level Security</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">⚡</div>
                        <div class="feature-text">Instant Processing</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📊</div>
                        <div class="feature-text">Real-Time Analytics</div>
                    </div>
                </div>

                <!-- Expiry Notice -->
                <div class="expiry-notice">
                    <div class="expiry-text">
                        <strong>⏰ Link Expiry:</strong> This verification link expires in 60 minutes. If it expires, you can request a new verification email from your dashboard.
                    </div>
                </div>

                <div class="divider"></div>

                <div class="content-text" style="font-size: 14px; color: #718096;">
                    Once verified, you'll be able to:
                    <ul style="margin-top: 10px; padding-left: 20px; color: #4a5568;">
                        <li>Access your business dashboard</li>
                        <li>Generate API keys for payment integration</li>
                        <li>View transaction history and analytics</li>
                        <li>Request account numbers for receiving payments</li>
                    </ul>
                </div>

                <div class="content-text" style="margin-top: 30px; font-size: 14px; color: #718096;">
                    If you have any questions or need assistance, our support team is here to help.
                </div>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-text">
                    This is an automated email from {{ $appName }}. Please do not reply to this email.
                </div>
                <div class="footer-text" style="font-size: 12px; color: #718096;">
                    © {{ date('Y') }} {{ $appName }}. All rights reserved.
                </div>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Support</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
