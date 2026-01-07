const axios = require('axios');

class WebhookSender {
  constructor() {
    this.timeout = 30000; // 30 seconds timeout
  }

  async sendApproval(payment) {
    if (!payment.webhookUrl) {
      console.error('[WebhookSender] No webhook URL provided for payment:', payment.transactionId);
      return;
    }

    try {
      const payload = {
        success: true,
        status: 'approved',
        transactionId: payment.transactionId,
        amount: payment.amount,
        payerName: payment.payerName,
        bank: payment.bank,
        approvedAt: new Date().toISOString(),
        message: 'Payment has been verified and approved'
      };

      console.log(`[WebhookSender] Sending approval webhook for transaction ${payment.transactionId}`);
      console.log(`[WebhookSender] Webhook URL: ${payment.webhookUrl}`);

      const response = await axios.post(payment.webhookUrl, payload, {
        timeout: this.timeout,
        headers: {
          'Content-Type': 'application/json',
          'User-Agent': 'EmailPaymentGateway/1.0'
        }
      });

      console.log(`[WebhookSender] ✅ Approval webhook sent successfully: ${response.status}`);
      return { success: true, response: response.data };
    } catch (error) {
      console.error(`[WebhookSender] ❌ Error sending approval webhook:`, error.message);
      
      if (error.response) {
        console.error(`[WebhookSender] Response status: ${error.response.status}`);
        console.error(`[WebhookSender] Response data:`, error.response.data);
      }

      // Retry logic could be added here
      return { success: false, error: error.message };
    }
  }

  async sendRejection(payment, reason) {
    if (!payment.webhookUrl) {
      console.error('[WebhookSender] No webhook URL provided for payment:', payment.transactionId);
      return;
    }

    try {
      const payload = {
        success: false,
        status: 'rejected',
        transactionId: payment.transactionId,
        amount: payment.amount,
        reason: reason,
        rejectedAt: new Date().toISOString(),
        message: 'Payment verification failed'
      };

      console.log(`[WebhookSender] Sending rejection webhook for transaction ${payment.transactionId}`);
      console.log(`[WebhookSender] Reason: ${reason}`);

      const response = await axios.post(payment.webhookUrl, payload, {
        timeout: this.timeout,
        headers: {
          'Content-Type': 'application/json',
          'User-Agent': 'EmailPaymentGateway/1.0'
        }
      });

      console.log(`[WebhookSender] ✅ Rejection webhook sent successfully: ${response.status}`);
      return { success: true, response: response.data };
    } catch (error) {
      console.error(`[WebhookSender] ❌ Error sending rejection webhook:`, error.message);
      return { success: false, error: error.message };
    }
  }
}

module.exports = WebhookSender;
