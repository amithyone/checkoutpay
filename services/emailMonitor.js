const Imap = require('imap');
const { simpleParser } = require('mailparser');
const cron = require('node-cron');

class EmailMonitor {
  constructor(paymentMatcher) {
    this.paymentMatcher = paymentMatcher;
    this.imap = null;
    this.isMonitoring = false;
    this.processedEmails = new Set();
    this.checkInterval = null;
    
    // Email configuration from environment
    this.config = {
      user: process.env.EMAIL_USER,
      password: process.env.EMAIL_PASSWORD,
      host: process.env.EMAIL_HOST || 'imap.gmail.com',
      port: parseInt(process.env.EMAIL_PORT || '993'),
      tls: process.env.EMAIL_TLS !== 'false',
      tlsOptions: { rejectUnauthorized: false }
    };
  }

  start() {
    if (!this.config.user || !this.config.password) {
      console.error('[EmailMonitor] ⚠️  Email credentials not configured. Set EMAIL_USER and EMAIL_PASSWORD in .env');
      return;
    }

    if (this.isMonitoring) {
      console.log('[EmailMonitor] Already monitoring emails');
      return;
    }

    this.connect();
    
    // Check for new emails every 30 seconds
    this.checkInterval = cron.schedule('*/30 * * * * *', () => {
      this.checkEmails();
    });

    this.isMonitoring = true;
    console.log('[EmailMonitor] ✅ Email monitoring started');
  }

  stop() {
    if (this.checkInterval) {
      this.checkInterval.stop();
    }
    if (this.imap) {
      this.imap.end();
    }
    this.isMonitoring = false;
    console.log('[EmailMonitor] ⏹️  Email monitoring stopped');
  }

  connect() {
    this.imap = new Imap(this.config);

    this.imap.once('ready', () => {
      console.log('[EmailMonitor] 📧 Connected to email server');
      this.checkEmails();
    });

    this.imap.once('error', (err) => {
      console.error('[EmailMonitor] Connection error:', err);
      // Retry connection after 30 seconds
      setTimeout(() => {
        if (this.isMonitoring) {
          console.log('[EmailMonitor] Retrying connection...');
          this.connect();
        }
      }, 30000);
    });

    this.imap.once('end', () => {
      console.log('[EmailMonitor] Connection ended');
      if (this.isMonitoring) {
        // Reconnect after 10 seconds
        setTimeout(() => {
          console.log('[EmailMonitor] Reconnecting...');
          this.connect();
        }, 10000);
      }
    });

    this.imap.connect();
  }

  checkEmails() {
    if (!this.imap || !this.imap.state || this.imap.state !== 'authenticated') {
      return;
    }

    this.imap.openBox('INBOX', false, (err, box) => {
      if (err) {
        console.error('[EmailMonitor] Error opening inbox:', err);
        return;
      }

      // Search for unread emails from the last 24 hours
      const yesterday = new Date();
      yesterday.setDate(yesterday.getDate() - 1);
      
      this.imap.search(['UNSEEN', ['SINCE', yesterday]], (err, results) => {
        if (err) {
          console.error('[EmailMonitor] Error searching emails:', err);
          return;
        }

        if (!results || results.length === 0) {
          return;
        }

        console.log(`[EmailMonitor] Found ${results.length} new email(s)`);

        const fetch = this.imap.fetch(results, { bodies: '' });

        fetch.on('message', (msg, seqno) => {
          msg.on('body', (stream, info) => {
            simpleParser(stream, (err, parsed) => {
              if (err) {
                console.error('[EmailMonitor] Error parsing email:', err);
                return;
              }

              this.processEmail(parsed);
            });
          });

          msg.once('attributes', (attrs) => {
            const uid = attrs.uid;
            // Mark as read after processing
            this.imap.addFlags(uid, '\\Seen', (err) => {
              if (err) {
                console.error('[EmailMonitor] Error marking email as read:', err);
              }
            });
          });
        });

        fetch.once('error', (err) => {
          console.error('[EmailMonitor] Error fetching emails:', err);
        });
      });
    });
  }

  processEmail(email) {
    const emailId = email.messageId || `${email.date}-${email.from?.text}`;
    
    // Skip if already processed
    if (this.processedEmails.has(emailId)) {
      return;
    }

    this.processedEmails.add(emailId);
    
    // Extract payment information from email
    const emailData = {
      subject: email.subject || '',
      from: email.from?.text || '',
      text: email.text || '',
      html: email.html || '',
      date: email.date
    };

    console.log(`[EmailMonitor] Processing email: ${emailData.subject}`);
    console.log(`[EmailMonitor] From: ${emailData.from}`);

    // Send to payment matcher to check against pending payments
    this.paymentMatcher.checkEmail(emailData);
  }

  isMonitoring() {
    return this.isMonitoring;
  }
}

module.exports = EmailMonitor;
