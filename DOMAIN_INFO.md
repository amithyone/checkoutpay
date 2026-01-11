# Domain Information

## 🌐 Production Domain

**Domain:** `check-outpay.com`

## 📍 Important URLs

### Admin Panel
- **URL:** `https://check-outpay.com/admin`
- **Login:** `admin@paymentgateway.com` / `password`

### Business Dashboard
- **Registration:** `https://check-outpay.com/dashboard/register`
- **Login:** `https://check-outpay.com/dashboard/login`
- **Dashboard:** `https://check-outpay.com/dashboard`

### API Endpoints
- **Base URL:** `https://check-outpay.com/api/v1`
- **Health Check:** `https://check-outpay.com/api/health`

### Cron Jobs
- **Direct Email Reading:** `https://check-outpay.com/cron/read-emails-direct`
- **IMAP Email Fetching:** `https://check-outpay.com/cron/monitor-emails`
- **Global Match:** `https://check-outpay.com/cron/global-match`

### Setup
- **Setup Wizard:** `https://check-outpay.com/setup`

## 🔧 Configuration

Make sure your `.env` file has:

```env
APP_URL=https://check-outpay.com
APP_ENV=production
```

## 📝 Notes

- All URLs use HTTPS for security
- Domain is configured for production use
- All endpoints are accessible via the domain above
