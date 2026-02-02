# CheckoutPay - Intelligent Payment Gateway

CheckoutPay is an intelligent payment gateway solution that enables businesses to accept payments via bank transfers with automatic verification and reconciliation.

## Features

- **Intelligent Payment Processing**: Smart payment verification and automatic reconciliation
- **Competitive Rates**: Just 1% + ₦50 per transaction - the finest rates in the market
- **API Integration**: RESTful API for seamless integration
- **Hosted Checkout**: Ready-to-use hosted payment page option
- **Real-time Webhooks**: Instant notifications for payment status updates
- **Secure & Reliable**: Bank-level security with encrypted transactions
- **Dashboard & Analytics**: Comprehensive dashboard for transaction management

## Quick Start

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

### Configuration

1. Configure your database in `.env`
2. Set up your business account in the admin panel
3. Get your API key from the business dashboard
4. Start accepting payments!

## Documentation

- [API Documentation](docs/API_DOCUMENTATION.md)
- [Setup Guide](docs/SETUP_GUIDE.md)
- [Admin Panel Guide](docs/ADMIN_PANEL.md)

## API Base URL

```
Production: https://check-outpay.com/api/v1
Development: http://localhost:8000/api/v1
```

## Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Laravel 10.x

## License

MIT

## Support

For support, contact us through the business dashboard or create an issue on GitHub.
