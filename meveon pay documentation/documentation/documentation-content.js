// ================================
// MEVONPAY API Documentation Content
// Complete documentation database for all API endpoints
// ================================

const documentation = {
  home: {
    title: '',
    subtitle: '',
    sections: [
      {
        title: '',
        icon: '',
        content: `
          <div class="hero-section">
            <div class="hero-actions">
              <a href="#/overview" class="btn-hero">Open Implementation Docs</a>
              <a href="https://mevonpay.com.ng/en/developers" target="_blank" class="btn-hero outline">Developer Portal</a>
            </div>
          </div>
          <p>This documentation was updated from the tested implementation in <code>MEVONPAY_IMPLEMENTATION.md</code>.</p>
          <div class="alert alert-info">
            <strong>Status:</strong> Internal implementation reference aligned to working production behavior.
          </div>
        `
      }
    ]
  },
  overview: {
    title: 'Implementation Overview',
    subtitle: 'Current tested MevonPay subsystem scope',
    sections: [
      {
        title: 'Scope',
        icon: 'fas fa-layer-group',
        content: `
          <ul>
            <li>Merchant checkout VA creation (dynamic + temp)</li>
            <li>Webhook processing and payment approval</li>
            <li>Business/provider assignment with external/hybrid/internal modes</li>
            <li>Bank list sync and name enquiry integrations</li>
            <li>Transfer payout flow via <code>/V1/createtransfer</code></li>
            <li>WhatsApp and rentals extensions using Mevon/MevonRubies</li>
          </ul>
        `
      },
      {
        title: 'Authentication',
        icon: 'fas fa-shield-alt',
        content: `
          <p>Send the secret key in <code>Authorization</code> exactly as configured for the endpoint behavior.</p>
          <div class="code-block mini"><pre><code class="language-http">Authorization: YOUR_SECRET_KEY
Content-Type: application/json
Accept: application/json</code></pre></div>
          <div class="alert alert-warning">
            Some provider paths may accept <code>Bearer YOUR_SECRET_KEY</code>, but implementation should follow tested endpoint behavior.
          </div>
        `
      }
    ]
  },
  components: {
    title: 'Integration Components',
    subtitle: 'Core services and assignment controls',
    sections: [
      {
        title: 'Core Services',
        icon: 'fas fa-cubes',
        content: `
          <ul>
            <li><code>app/Services/MevonPayVirtualAccountService.php</code> - <code>/V1/createtempva.php</code>, <code>/V1/createdynamic</code></li>
            <li><code>app/Http/Controllers/Api/MevonPayWebhookController.php</code> - handles <code>funding.success</code> and approval flow</li>
            <li><code>app/Services/MevonPayBankService.php</code> - <code>/V1/bank_service</code> for bank list and name enquiry</li>
            <li><code>app/Services/MavonPayTransferService.php</code> - <code>/V1/createtransfer</code> payouts and normalization</li>
          </ul>
        `
      },
      {
        title: 'Assignment and Payment Routing',
        icon: 'fas fa-random',
        content: `
          <ul>
            <li><code>Business</code> supports <code>external_only</code>, <code>hybrid</code>, <code>internal_only</code> modes</li>
            <li>VA generation mode: <code>dynamic</code> or <code>temp</code></li>
            <li><code>PaymentService</code> creates external pending payments and handles fallback in hybrid mode</li>
            <li><code>AccountNumberService</code> resolves external-first account assignment with internal fallback where allowed</li>
          </ul>
        `
      }
    ]
  },
  endpoints: {
    title: 'Project Endpoints',
    subtitle: 'Active routes in this codebase',
    sections: [
      {
        title: 'Webhook Routes',
        icon: 'fas fa-bell',
        content: `
          <ul>
            <li><code>POST /api/v1/webhook/mevonpay</code></li>
            <li><code>POST /api/v1/webhooks/mevonpay</code></li>
            <li><code>POST /api/v1/webhook/sla</code></li>
            <li><code>POST /api/v1/webhooks/sla</code></li>
            <li><code>POST /api/v1/webhook/mavonpay</code></li>
            <li><code>POST /api/v1/webhooks/mavonpay</code></li>
          </ul>
          <p>Handled by <code>MevonPayWebhookController::receive</code>.</p>
        `
      },
      {
        title: 'Admin and Test Routes',
        icon: 'fas fa-tools',
        content: `
          <ul>
            <li><code>POST /api/v1/test-meveon</code></li>
            <li><code>GET /api/v1/test-meveon</code></li>
            <li><code>POST /admin/test-transaction/mevonpay-temp-va</code></li>
            <li><code>POST /admin/test-transaction/mevonpay-dynamic-va</code></li>
            <li><code>GET /admin/external-apis</code></li>
            <li><code>PUT /admin/external-apis/{externalApi}/businesses</code></li>
            <li><code>GET /admin/external-apis/mevonpay/webhook-sources</code></li>
          </ul>
        `
      }
    ]
  },
  flows: {
    title: 'Runtime Flows',
    subtitle: 'Verified checkout, webhook, bank, and transfer logic',
    sections: [
      {
        title: 'Checkout VA Flow',
        icon: 'fas fa-shopping-cart',
        content: `
          <ol>
            <li>Payment enters <code>PaymentService::createPayment</code></li>
            <li>Business mode determines external/hybrid/internal behavior</li>
            <li>VA mode selects <code>createTempVa</code> or <code>createDynamicVa</code></li>
            <li>Success creates external account row and pending payment</li>
            <li>Hybrid failure falls back to internal account assignment</li>
            <li>External-only failure returns error</li>
          </ol>
        `
      },
      {
        title: 'Webhook Approval Flow',
        icon: 'fas fa-check-circle',
        content: `
          <ol>
            <li>Webhook route reaches <code>MevonPayWebhookController::receive</code></li>
            <li>Optional source and secret checks are applied</li>
            <li>Only <code>funding.success</code> is processed</li>
            <li>Match by <code>data.account_number</code></li>
            <li>Matching pending payment is approved and balance updated</li>
            <li>No match attempts WhatsApp top-up handlers before safe success response</li>
          </ol>
        `
      },
      {
        title: 'Transfer Normalization',
        icon: 'fas fa-exchange-alt',
        content: `
          <table>
            <thead><tr><th>Provider Code</th><th>Normalized Status</th></tr></thead>
            <tbody>
              <tr><td><code>00</code></td><td><strong>successful</strong></td></tr>
              <tr><td><code>09</code>, <code>90</code>, <code>99</code></td><td><strong>pending</strong></td></tr>
              <tr><td>others</td><td><strong>failed</strong></td></tr>
            </tbody>
          </table>
          <p>Implementation also supports success-message fallback and empty-2xx success edge case handling.</p>
        `
      }
    ]
  },
  configuration: {
    title: 'Configuration',
    subtitle: 'Environment variables used by tested integration',
    sections: [
      {
        title: 'Core and Webhook Security',
        icon: 'fas fa-key',
        content: `
          <ul>
            <li><code>MEVONPAY_BASE_URL</code></li>
            <li><code>MEVONPAY_SECRET_KEY</code></li>
            <li><code>MEVONPAY_WEBHOOK_SECRET</code> (fallback chain includes SLA/MAVONPAY secrets)</li>
            <li><code>MEVONPAY_WEBHOOK_ALLOWED_IPS</code></li>
            <li><code>MEVONPAY_WEBHOOK_ALLOWED_DOMAINS</code></li>
          </ul>
        `
      },
      {
        title: 'Transfers, Timeouts, and Rubies',
        icon: 'fas fa-sliders-h',
        content: `
          <ul>
            <li><code>MEVONPAY_DEBIT_ACCOUNT_NAME</code>, <code>MEVONPAY_DEBIT_ACCOUNT_NUMBER</code>, <code>MEVONPAY_CURRENT_PASSWORD</code></li>
            <li><code>MEVONPAY_TIMEOUT_SECONDS</code>, <code>MEVONPAY_CONNECT_TIMEOUT_SECONDS</code></li>
            <li><code>MEVONPAY_ACCOUNT_LOGS_ENABLED</code></li>
            <li><code>MEVONPAY_TEMP_VA_REGISTRATION_NUMBER</code></li>
            <li><code>MEVONRUBIES_BASE_URL</code>, <code>MEVONRUBIES_SECRET_KEY</code>, <code>MEVONRUBIES_TIMEOUT_SECONDS</code>, <code>MEVONRUBIES_CREATE_PATH</code></li>
          </ul>
        `
      }
    ]
  },
  resiliency: {
    title: 'Resiliency and Hardening',
    subtitle: 'Implemented safeguards from production behavior',
    sections: [
      {
        title: 'Handled Cases',
        icon: 'fas fa-shield-virus',
        content: `
          <ul>
            <li>Mixed authorization/header format support</li>
            <li>Webhook allowlist and secret validation when configured</li>
            <li>Safe ignore for non-<code>funding.success</code> events</li>
            <li>Name enquiry timeout/empty reply fallback handling</li>
            <li>Bank code normalization and legacy mapping fallback</li>
            <li>Hybrid-mode external failure fallback to internal account flow</li>
            <li>Rubies payload parser supports root and nested <code>data</code> shapes</li>
          </ul>
        `
      }
    ]
  },
  operations: {
    title: 'Operations Checklist',
    subtitle: 'Verification and deployment checks',
    sections: [
      {
        title: 'Operational Commands and Checks',
        icon: 'fas fa-tasks',
        content: `
          <ul>
            <li><code>php artisan banks:sync</code></li>
            <li><code>php artisan banks:sync --no-fallback</code></li>
            <li><code>php artisan mevon:rubies-test-initiate --fname=John --lname=Doe --phone=08012345678 --dob=1990-01-01 --email=test@example.com --bvn=12345678901</code></li>
            <li>Verify external API assignment, provider mode, VA mode, and service scoping in admin panel</li>
            <li>Confirm webhook source diagnostics at <code>/admin/external-apis/mevonpay/webhook-sources</code></li>
          </ul>
        `
      }
    ]
  }
};

// Make documentation available globally
window.documentation = documentation;
