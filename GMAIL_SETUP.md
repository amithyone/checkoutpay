# Gmail Setup Guide

This guide will help you configure Gmail monitoring for the Email Payment Gateway.

## ✅ Gmail is Fully Supported!

The gateway is already configured to work with Gmail. You just need to set up your Gmail account properly.

## 📋 Step-by-Step Setup
 
**Solution:**
- Verify `EMAIL_HOST=imap.gmail.com`
- Check `EMAIL_PORT=993`
- Ensure `EMAIL_ENCRYPTION=ssl`

### No emails being processed

**Solution:**
- Make sure emails are actually in your inbox
- Check that emails are unread (the system only processes unread emails)
- Verify the scheduler is running: `php artisan schedule:work`
- Check logs: `storage/logs/laravel.log`

## 🔒 Security Best Practices

1. **Never commit `.env` file** - It contains sensitive credentials
2. **Use App Passwords** - More secure than regular passwords
3. **Rotate App Passwords** - Change them periodically
4. **Monitor Access** - Check Google Account activity regularly
5. **Use Separate Email** - Consider a dedicated Gmail account for payments

## 📊 Monitoring Status

Check if email monitoring is working:

```bash
# Check scheduled tasks
php artisan schedule:list

# Run manually
php artisan payment:monitor-emails

# Check queue status
php artisan queue:work --verbose
```

## 🚀 Production Setup

For production, make sure:

1. **Queue Worker** is running (use Supervisor/systemd)
2. **Scheduler** is in cron: `* * * * * php artisan schedule:run`
3. **Logs** are monitored: `tail -f storage/logs/laravel.log`
4. **Email credentials** are secure and rotated regularly

## 💡 Tips

- **Dedicated Account**: Use a separate Gmail account just for payment notifications
- **Filters**: Set up Gmail filters to organize payment emails
- **Labels**: Create labels for better email organization
- **Backup**: Keep a backup of your App Password in a secure password manager

## 📞 Need Help?

If you're still having issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode: `APP_DEBUG=true` in `.env`
3. Test connection manually: `php artisan payment:monitor-emails`
4. Verify Gmail settings in Google Account security page

---

**That's it!** Your Gmail account is now configured for payment monitoring. 🎉
