class PaymentMatcher {
  constructor(paymentStore, webhookSender) {
    this.paymentStore = paymentStore;
    this.webhookSender = webhookSender;
  }

  checkEmail(emailData) {
    const pendingPayments = this.paymentStore.getPendingPayments();
    
    if (pendingPayments.length === 0) {
      console.log('[PaymentMatcher] No pending payments to match');
      return;
    }

    // Extract payment information from email
    const emailPaymentInfo = this.extractPaymentInfo(emailData);

    if (!emailPaymentInfo) {
      console.log('[PaymentMatcher] Could not extract payment info from email');
      return;
    }

    console.log('[PaymentMatcher] Extracted payment info:', emailPaymentInfo);

    // Check each pending payment
    for (const pendingPayment of pendingPayments) {
      const match = this.matchPayment(pendingPayment, emailPaymentInfo);
      
      if (match.matched) {
        console.log(`[PaymentMatcher] ✅ Payment matched for transaction ${pendingPayment.transactionId}`);
        
        // Update payment status
        this.paymentStore.updatePayment(pendingPayment.transactionId, {
          status: 'approved',
          matchedAt: new Date().toISOString(),
          emailData: emailPaymentInfo
        });

        // Send approval webhook
        this.webhookSender.sendApproval(pendingPayment);
        return;
      } else if (match.reason) {
        console.log(`[PaymentMatcher] ❌ Payment mismatch for transaction ${pendingPayment.transactionId}: ${match.reason}`);
      }
    }

    console.log('[PaymentMatcher] No matching payment found for this email');
  }

  extractPaymentInfo(emailData) {
    const text = (emailData.text || '').toLowerCase();
    const html = (emailData.html || '').toLowerCase();
    const subject = (emailData.subject || '').toLowerCase();
    const from = (emailData.from || '').toLowerCase();

    // Combine all text for searching
    const fullText = `${subject} ${text} ${html}`;

    // Extract amount - look for currency patterns
    const amountPatterns = [
      /(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]*[₦$]?\s*([\d,]+\.?\d*)/i,
      /[₦$]\s*([\d,]+\.?\d*)/i,
      /([\d,]+\.?\d*)\s*(?:naira|ngn|usd|dollar)/i,
      /([\d,]+\.?\d*)/i
    ];

    let amount = null;
    for (const pattern of amountPatterns) {
      const match = fullText.match(pattern);
      if (match) {
        amount = parseFloat(match[1].replace(/,/g, ''));
        if (amount > 0) {
          break;
        }
      }
    }

    // Extract sender name - look for name patterns
    const namePatterns = [
      /(?:from|sender|payer|depositor|account\s*name|name)[\s:]*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i,
      /([A-Z][a-z]+\s+[A-Z][a-z]+)/g,
      /(?:credited\s+by|from)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i
    ];

    let senderName = null;
    for (const pattern of namePatterns) {
      const match = fullText.match(pattern);
      if (match) {
        senderName = match[1].trim().toLowerCase();
        break;
      }
    }

    // If no name found in body, try extracting from email sender
    if (!senderName && from) {
      const fromMatch = from.match(/([^<]+)/);
      if (fromMatch) {
        senderName = fromMatch[1].trim().toLowerCase();
      }
    }

    if (!amount) {
      return null;
    }

    return {
      amount: amount,
      senderName: senderName,
      emailSubject: emailData.subject,
      emailFrom: emailData.from,
      extractedAt: new Date().toISOString()
    };
  }

  matchPayment(pendingPayment, emailPaymentInfo) {
    // Check amount match (allow small tolerance for rounding)
    const amountDiff = Math.abs(pendingPayment.amount - emailPaymentInfo.amount);
    const amountTolerance = 0.01; // 1 kobo tolerance
    
    if (amountDiff > amountTolerance) {
      return {
        matched: false,
        reason: `Amount mismatch: expected ${pendingPayment.amount}, got ${emailPaymentInfo.amount}`
      };
    }

    // If payer name is provided, it must match exactly (case-insensitive)
    if (pendingPayment.payerName) {
      if (!emailPaymentInfo.senderName) {
        return {
          matched: false,
          reason: 'Payer name required but not found in email'
        };
      }

      // Normalize names for comparison (remove extra spaces, trim)
      const expectedName = pendingPayment.payerName.trim().toLowerCase().replace(/\s+/g, ' ');
      const receivedName = emailPaymentInfo.senderName.trim().toLowerCase().replace(/\s+/g, ' ');

      // Exact match required
      if (expectedName !== receivedName) {
        return {
          matched: false,
          reason: `Name mismatch: expected "${expectedName}", got "${receivedName}"`
        };
      }
    }

    // Amount matches and name matches (if required)
    return {
      matched: true,
      reason: 'Amount and name match'
    };
  }
}

module.exports = PaymentMatcher;
