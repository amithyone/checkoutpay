const fs = require('fs');
const path = require('path');

class PaymentStore {
  constructor() {
    this.storagePath = path.join(__dirname, '../data/payments.json');
    this.payments = new Map();
    this.loadPayments();
  }

  loadPayments() {
    try {
      // Ensure data directory exists
      const dataDir = path.dirname(this.storagePath);
      if (!fs.existsSync(dataDir)) {
        fs.mkdirSync(dataDir, { recursive: true });
      }

      // Load existing payments
      if (fs.existsSync(this.storagePath)) {
        const data = fs.readFileSync(this.storagePath, 'utf8');
        const paymentsArray = JSON.parse(data);
        paymentsArray.forEach(payment => {
          this.payments.set(payment.transactionId, payment);
        });
        console.log(`[PaymentStore] Loaded ${paymentsArray.length} payments from storage`);
      }
    } catch (error) {
      console.error('[PaymentStore] Error loading payments:', error);
    }
  }

  savePayments() {
    try {
      const paymentsArray = Array.from(this.payments.values());
      fs.writeFileSync(this.storagePath, JSON.stringify(paymentsArray, null, 2));
    } catch (error) {
      console.error('[PaymentStore] Error saving payments:', error);
    }
  }

  addPayment(payment) {
    this.payments.set(payment.transactionId, payment);
    this.savePayments();
    console.log(`[PaymentStore] Added payment: ${payment.transactionId}`);
  }

  getPayment(transactionId) {
    return this.payments.get(transactionId) || null;
  }

  getAllPayments() {
    return Array.from(this.payments.values());
  }

  getPendingPayments() {
    return Array.from(this.payments.values()).filter(p => p.status === 'pending');
  }

  updatePayment(transactionId, updates) {
    const payment = this.payments.get(transactionId);
    if (payment) {
      Object.assign(payment, updates);
      payment.updatedAt = new Date().toISOString();
      this.payments.set(transactionId, payment);
      this.savePayments();
      console.log(`[PaymentStore] Updated payment: ${transactionId}`);
      return payment;
    }
    return null;
  }

  deletePayment(transactionId) {
    const deleted = this.payments.delete(transactionId);
    if (deleted) {
      this.savePayments();
      console.log(`[PaymentStore] Deleted payment: ${transactionId}`);
    }
    return deleted;
  }
}

module.exports = PaymentStore;
