# Ticket Selling System - Design Proposal

## Overview
This document outlines the proposed architecture for adding a **Ticket Selling System** to the existing CheckoutPay payment gateway. The ticket system will be a **NEW SERVICE** that integrates with the existing payment infrastructure without replacing or breaking the current payment gateway functionality.

---

## 🎯 Core Requirements

1. ✅ **Ticket Selling Service** - Businesses can create and sell event tickets
2. ✅ **QR Code Generation** - Every ticket gets a unique QR code for verification
3. ✅ **Admin Management** - Admins can manage events, tickets, and verifications
4. ✅ **Payment Integration** - Uses existing CheckoutPay payment gateway
5. ✅ **Non-Breaking** - Does not affect existing payment gateway functionality

---

## 📊 Database Schema Design

### 1. Events Table (`events`)
Stores event information created by businesses.

```sql
- id (bigint, primary)
- business_id (foreign key → businesses)
- title (string)
- description (text)
- venue (string)
- start_date (datetime)
- end_date (datetime)
- timezone (string, default: Africa/Lagos)
- cover_image (string, nullable)
- status (enum: draft, published, cancelled, completed)
- max_attendees (integer, nullable)
- created_at, updated_at, deleted_at
```

### 2. Ticket Types Table (`ticket_types`)
Different ticket categories for an event (VIP, Regular, Early Bird, etc.)

```sql
- id (bigint, primary)
- event_id (foreign key → events)
- name (string) - e.g., "VIP", "Regular", "Early Bird"
- description (text, nullable)
- price (decimal 10,2)
- quantity_available (integer)
- quantity_sold (integer, default: 0)
- sales_start_date (datetime, nullable)
- sales_end_date (datetime, nullable)
- is_active (boolean, default: true)
- created_at, updated_at, deleted_at
```

### 3. Ticket Orders Table (`ticket_orders`)
Stores customer ticket purchases.

```sql
- id (bigint, primary)
- event_id (foreign key → events)
- business_id (foreign key → businesses)
- order_number (string, unique) - e.g., "TKT-20260127-ABC123"
- customer_name (string)
- customer_email (string)
- customer_phone (string, nullable)
- total_amount (decimal 10,2)
- payment_id (foreign key → payments) - Links to existing payment system
- payment_status (enum: pending, paid, failed, refunded)
- status (enum: pending, confirmed, cancelled)
- purchased_at (datetime)
- created_at, updated_at, deleted_at
```

### 4. Tickets Table (`tickets`)
Individual tickets within an order (one order can have multiple tickets).

```sql
- id (bigint, primary)
- ticket_order_id (foreign key → ticket_orders)
- ticket_type_id (foreign key → ticket_types)
- ticket_number (string, unique) - e.g., "TKT-20260127-ABC123-001"
- qr_code (string, unique) - Base64 or file path
- qr_code_data (text) - JSON data encoded in QR
- status (enum: valid, used, cancelled, refunded)
- checked_in_at (datetime, nullable)
- checked_in_by (foreign key → admins, nullable)
- created_at, updated_at, deleted_at
```

### 5. Ticket Check-ins Table (`ticket_check_ins`)
Logs all QR code scans/verifications.

```sql
- id (bigint, primary)
- ticket_id (foreign key → tickets)
- checked_in_by (foreign key → admins)
- check_in_method (enum: qr_scan, manual)
- location (string, nullable) - GPS or venue location
- notes (text, nullable)
- created_at
```

---

## 🔄 Integration with Existing Payment System

### Payment Flow:
1. Customer selects tickets → Creates `ticket_order` with `payment_status: pending`
2. System creates a **Payment** record using existing `PaymentService`
3. Customer pays via existing CheckoutPay gateway
4. When payment is approved → Update `ticket_order.payment_status = 'paid'`
5. Generate tickets with QR codes
6. Send ticket email to customer

### Key Integration Points:
- **Reuse Payment Model**: Link `ticket_orders.payment_id` → `payments.id`
- **Reuse PaymentService**: Use existing payment creation logic
- **Reuse Webhook System**: Send ticket-specific webhooks when payment approved
- **Reuse Email System**: Send ticket emails using existing email infrastructure

---

## 🎨 User Interfaces

### 1. Business Dashboard (`/dashboard/tickets`)
- **Events List**: View all events created by business
- **Create Event**: Form to create new events
- **Event Details**: View event, ticket types, sales stats
- **Ticket Orders**: View all ticket purchases
- **Analytics**: Sales reports, attendance tracking

### 2. Public Ticket Page (`/tickets/{event-slug}`)
- **Event Display**: Show event details, ticket types, pricing
- **Ticket Selection**: Customer selects quantity per ticket type
- **Checkout**: Redirects to existing `/pay` page with ticket metadata
- **Ticket Download**: After payment, show/download tickets with QR codes

### 3. Admin Panel (`/admin/tickets`)
- **Events Management**: View all events across all businesses
- **Ticket Orders**: View all ticket purchases
- **QR Code Scanner**: Mobile-friendly scanner for check-ins
- **Check-in Logs**: View all check-ins and verifications
- **Reports**: Sales analytics, attendance reports

### 4. QR Code Scanner (`/admin/tickets/scanner`)
- **Mobile-Optimized**: Full-screen scanner interface
- **Real-time Validation**: Instant verification of QR codes
- **Check-in Actions**: Mark ticket as used, add notes
- **Offline Support**: Cache valid tickets for offline scanning

---

## 🔐 QR Code Implementation

### QR Code Data Structure:
```json
{
  "ticket_id": 12345,
  "ticket_number": "TKT-20260127-ABC123-001",
  "event_id": 100,
  "order_id": 500,
  "verification_token": "abc123xyz789",
  "expires_at": "2026-01-28T10:00:00Z"
}
```

### QR Code Generation:
- **Library**: Use `simplesoftwareio/simple-qrcode` (Laravel package)
- **Format**: PNG or SVG
- **Size**: 300x300px minimum
- **Storage**: Store in `storage/app/public/tickets/qr-codes/`
- **Security**: Include verification token to prevent forgery

### QR Code Verification:
1. Scan QR code → Extract ticket_id and verification_token
2. Query database → Verify ticket exists and is valid
3. Check status → Ensure ticket is not already used
4. Validate event → Ensure event hasn't been cancelled
5. Record check-in → Create `ticket_check_ins` record
6. Update ticket → Set `status = 'used'`, `checked_in_at = now()`

---

## 📁 File Structure

```
app/
├── Models/
│   ├── Event.php
│   ├── TicketType.php
│   ├── TicketOrder.php
│   ├── Ticket.php
│   └── TicketCheckIn.php
├── Http/
│   ├── Controllers/
│   │   ├── Business/
│   │   │   ├── EventController.php
│   │   │   └── TicketOrderController.php
│   │   ├── Admin/
│   │   │   ├── TicketController.php
│   │   │   └── TicketScannerController.php
│   │   └── Public/
│   │       └── TicketController.php
│   └── Requests/
│       ├── StoreEventRequest.php
│       └── PurchaseTicketRequest.php
├── Services/
│   ├── TicketService.php
│   ├── QRCodeService.php
│   └── TicketEmailService.php
├── Jobs/
│   └── GenerateTicketQRCodes.php
└── Events/
    └── TicketPurchased.php

database/
└── migrations/
    ├── 2026_01_27_000001_create_events_table.php
    ├── 2026_01_27_000002_create_ticket_types_table.php
    ├── 2026_01_27_000003_create_ticket_orders_table.php
    ├── 2026_01_27_000004_create_tickets_table.php
    └── 2026_01_27_000005_create_ticket_check_ins_table.php

resources/
└── views/
    ├── business/
    │   ├── tickets/
    │   │   ├── events/
    │   │   │   ├── index.blade.php
    │   │   │   ├── create.blade.php
    │   │   │   └── show.blade.php
    │   │   └── orders/
    │   │       └── index.blade.php
    ├── admin/
    │   └── tickets/
    │       ├── events/
    │       │   └── index.blade.php
    │       ├── orders/
    │       │   └── index.blade.php
    │       └── scanner.blade.php
    └── public/
        └── tickets/
            ├── show.blade.php
            └── download.blade.php
```

---

## 🔗 Routes Structure

### Business Routes (`routes/business.php`):
```php
Route::prefix('dashboard/tickets')->name('business.tickets.')->group(function () {
    Route::resource('events', EventController::class);
    Route::get('events/{event}/orders', [TicketOrderController::class, 'index'])->name('events.orders');
    Route::get('orders', [TicketOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [TicketOrderController::class, 'show'])->name('orders.show');
});
```

### Admin Routes (`routes/admin.php`):
```php
Route::prefix('admin/tickets')->name('admin.tickets.')->group(function () {
    Route::get('events', [TicketController::class, 'events'])->name('events.index');
    Route::get('orders', [TicketController::class, 'orders'])->name('orders.index');
    Route::get('scanner', [TicketScannerController::class, 'index'])->name('scanner');
    Route::post('scanner/verify', [TicketScannerController::class, 'verify'])->name('scanner.verify');
    Route::post('scanner/check-in', [TicketScannerController::class, 'checkIn'])->name('scanner.check-in');
});
```

### Public Routes (`routes/web.php`):
```php
Route::prefix('tickets')->name('tickets.')->group(function () {
    Route::get('{event}', [Public\TicketController::class, 'show'])->name('show');
    Route::post('{event}/purchase', [Public\TicketController::class, 'purchase'])->name('purchase');
    Route::get('order/{orderNumber}', [Public\TicketController::class, 'order'])->name('order');
    Route::get('order/{orderNumber}/download', [Public\TicketController::class, 'download'])->name('download');
});
```

---

## 🔄 Payment Integration Flow

### Step-by-Step Process:

1. **Customer browses event** (`/tickets/{event-slug}`)
   - Views event details and available ticket types
   - Selects quantities for each ticket type

2. **Customer initiates purchase** (`POST /tickets/{event}/purchase`)
   - Creates `ticket_order` with `payment_status: pending`
   - Calculates total amount
   - Creates `Payment` record using existing `PaymentService`
   - Redirects to `/pay/{transaction_id}` (existing checkout page)

3. **Customer pays** (Existing payment flow)
   - Uses existing CheckoutPay payment gateway
   - Payment is processed and matched via email

4. **Payment approved** (Event listener)
   - `PaymentApproved` event fires (existing)
   - New listener: `ProcessTicketOrderOnPayment`
   - Updates `ticket_order.payment_status = 'paid'`
   - Generates tickets with QR codes
   - Sends ticket email to customer

5. **Customer receives tickets**
   - Email with ticket PDFs (QR codes included)
   - Or download from `/tickets/order/{orderNumber}`

---

## 🎫 QR Code Scanner Features

### Admin Scanner Interface:
- **Camera Access**: Use device camera for scanning
- **Manual Entry**: Option to manually enter ticket number
- **Real-time Feedback**: Green/red indicators for valid/invalid
- **Check-in Actions**:
  - Mark as checked in
  - Add notes (e.g., "VIP section", "Late arrival")
  - View ticket details (customer name, ticket type, order info)
- **Offline Mode**: Cache valid tickets for offline scanning
- **Bulk Check-in**: Scan multiple tickets quickly

### Security Features:
- **Token Verification**: QR code includes verification token
- **One-time Use**: Ticket can only be checked in once
- **Expiration Check**: Verify ticket hasn't expired
- **Event Validation**: Ensure ticket matches current event
- **Admin Logging**: All check-ins logged with admin ID

---

## 📧 Email Notifications

### Ticket Purchase Confirmation:
- **Trigger**: When payment is approved
- **Recipient**: Customer email
- **Content**:
  - Order confirmation
  - Event details
  - Ticket PDFs with QR codes (attachments)
  - Download link

### Ticket Reminder:
- **Trigger**: 24 hours before event
- **Recipient**: Customer email
- **Content**: Event reminder + ticket download link

---

## 📊 Admin Management Features

### Events Management:
- View all events (with filters: status, business, date range)
- Edit/delete events
- View event analytics (tickets sold, revenue, attendance)

### Ticket Orders:
- View all orders (with filters: event, status, date range)
- View order details (customer info, tickets, payment status)
- Refund tickets (if needed)
- Export orders to CSV

### QR Code Scanner:
- Mobile-optimized scanner interface
- Real-time verification
- Check-in logging
- Attendance reports

---

## 🛡️ Security Considerations

1. **QR Code Security**:
   - Include verification token in QR data
   - Validate token on server-side
   - Prevent QR code duplication/reuse

2. **Access Control**:
   - Businesses can only manage their own events
   - Admins can view all events
   - Public can only purchase tickets (not create events)

3. **Payment Security**:
   - Reuse existing payment security
   - No new payment vulnerabilities introduced

4. **Data Privacy**:
   - Customer data encrypted
   - GDPR compliance for ticket data

---

## 🚀 Implementation Phases

### Phase 1: Core Infrastructure (Week 1)
- ✅ Database migrations
- ✅ Models and relationships
- ✅ Basic services (TicketService, QRCodeService)

### Phase 2: Business Dashboard (Week 2)
- ✅ Event creation/management
- ✅ Ticket type management
- ✅ Order viewing

### Phase 3: Public Ticket Purchase (Week 3)
- ✅ Public event page
- ✅ Ticket selection
- ✅ Payment integration
- ✅ Ticket generation

### Phase 4: Admin Features (Week 4)
- ✅ Admin event management
- ✅ QR code scanner
- ✅ Check-in system
- ✅ Reports and analytics

### Phase 5: Polish & Testing (Week 5)
- ✅ Email notifications
- ✅ PDF generation
- ✅ Mobile optimization
- ✅ Testing and bug fixes

---

## ✅ Approved Requirements

1. **Pricing Model**: ✅ **Commission per sale** - Charge commission on each ticket sale

2. **Ticket Limits**: ✅ **Set by business or admin** - Businesses can set max tickets per customer, admins can override

3. **Refunds**: ✅ **Manual approval** - Admin must approve refunds

4. **QR Code Format**: ✅ **PNG/SVG in PDF** - Users receive PDF of ticket with QR code included

5. **Ticket Design**: ✅ **Customizable with templates** - Businesses can add their own ticket design, but we provide templates and size guidelines

6. **Email Notifications**: ✅ **Yes** - Send email notifications for ticket sales

7. **Check-in Methods**: QR code scanner + manual entry option

---

## ✅ Non-Breaking Guarantees

- ✅ **Existing Payment Gateway**: Fully functional, no changes
- ✅ **Existing Routes**: All existing routes remain unchanged
- ✅ **Existing Models**: No modifications to existing models
- ✅ **Existing Services**: All existing services remain intact
- ✅ **Database**: New tables only, no modifications to existing tables
- ✅ **API**: Existing API endpoints unchanged

---

## 📝 Next Steps

1. **Review this design** - Discuss and refine
2. **Answer questions** - Clarify requirements
3. **Approve architecture** - Get go-ahead
4. **Start implementation** - Begin Phase 1

---

**Ready to discuss!** Please review and let me know:
- What you'd like to change or add
- Answers to the questions above
- Any concerns or suggestions
- When you're ready to proceed with implementation
