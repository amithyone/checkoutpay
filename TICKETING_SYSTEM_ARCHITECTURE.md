# Event Ticketing System - Architecture & Integration Plan

## Overview
Add a comprehensive event ticketing solution integrated with the existing payment gateway, allowing business owners to create/manage events and sell tickets, with admin oversight and public-facing event pages.

---

## 1. Database Schema

### Core Tables

#### `events` Table
```sql
- id (bigint, primary)
- business_id (bigint, foreign -> businesses.id)
- title (string, 255)
- slug (string, 255, unique)
- description (text)
- short_description (text, 500)
- event_image (string, nullable) - Featured image URL
- event_banner (string, nullable) - Banner image URL
- venue_name (string, 255)
- venue_address (text)
- venue_city (string, 100)
- venue_state (string, 100)
- venue_country (string, 100)
- start_date (datetime)
- end_date (datetime)
- timezone (string, 50, default: 'Africa/Lagos')
- status (enum: 'draft', 'published', 'cancelled', 'completed')
- is_featured (boolean, default: false)
- max_attendees (integer, nullable) - Total capacity
- current_attendees (integer, default: 0) - Sold tickets count
- registration_deadline (datetime, nullable)
- allow_waitlist (boolean, default: false)
- waitlist_capacity (integer, nullable)
- organizer_name (string, 255)
- organizer_email (string, 255)
- organizer_phone (string, 50)
- terms_and_conditions (text, nullable)
- refund_policy (text, nullable)
- social_links (json, nullable) - {facebook, twitter, instagram, website}
- seo_title (string, nullable)
- seo_description (text, nullable)
- created_at, updated_at, deleted_at (soft deletes)
```

#### `ticket_types` Table
```sql
- id (bigint, primary)
- event_id (bigint, foreign -> events.id)
- name (string, 255) - e.g., "VIP", "General Admission", "Early Bird"
- description (text, nullable)
- price (decimal, 10, 2)
- quantity (integer) - Total available tickets
- sold_quantity (integer, default: 0)
- min_per_order (integer, default: 1)
- max_per_order (integer, default: 10)
- sales_start_date (datetime, nullable)
- sales_end_date (datetime, nullable)
- is_active (boolean, default: true)
- sort_order (integer, default: 0)
- created_at, updated_at
```

#### `ticket_orders` Table
```sql
- id (bigint, primary)
- order_number (string, 50, unique) - e.g., "TKT-20260127-ABC123"
- event_id (bigint, foreign -> events.id)
- business_id (bigint, foreign -> businesses.id)
- customer_name (string, 255)
- customer_email (string, 255)
- customer_phone (string, 50, nullable)
- total_amount (decimal, 10, 2)
- payment_id (bigint, foreign -> payments.id, nullable) - Links to existing payment system
- payment_status (enum: 'pending', 'paid', 'failed', 'refunded')
- status (enum: 'pending', 'confirmed', 'cancelled', 'refunded')
- payment_method (string, nullable)
- metadata (json, nullable) - Additional order data
- notes (text, nullable)
- created_at, updated_at
```

#### `ticket_order_items` Table
```sql
- id (bigint, primary)
- order_id (bigint, foreign -> ticket_orders.id)
- ticket_type_id (bigint, foreign -> ticket_types.id)
- quantity (integer)
- unit_price (decimal, 10, 2)
- total_price (decimal, 10, 2)
- created_at, updated_at
```

#### `tickets` Table (Individual Tickets)
```sql
- id (bigint, primary)
- ticket_number (string, 50, unique) - e.g., "TKT-20260127-ABC123-001"
- order_id (bigint, foreign -> ticket_orders.id)
- ticket_type_id (bigint, foreign -> ticket_types.id)
- event_id (bigint, foreign -> events.id)
- attendee_name (string, 255)
- attendee_email (string, 255)
- qr_code (string, nullable) - QR code data/URL
- check_in_status (enum: 'not_checked_in', 'checked_in', 'cancelled')
- checked_in_at (datetime, nullable)
- checked_in_by (bigint, nullable) - Admin/user ID who checked in
- is_transferable (boolean, default: false)
- transferred_from_ticket_id (bigint, nullable)
- metadata (json, nullable)
- created_at, updated_at
```

#### `event_check_ins` Table (For tracking check-ins)
```sql
- id (bigint, primary)
- ticket_id (bigint, foreign -> tickets.id)
- event_id (bigint, foreign -> events.id)
- checked_in_by (bigint, nullable) - Admin/user ID
- check_in_method (enum: 'qr_scan', 'manual', 'api')
- check_in_time (datetime)
- notes (text, nullable)
- created_at
```

---

## 2. Integration with Existing Payment System

### Payment Flow
1. **Customer selects tickets** → Creates `ticket_order` with status `pending`
2. **Generate payment request** → Use existing `Payment` model/API
3. **Link payment to order** → `ticket_order.payment_id` = `payment.id`
4. **Payment approved** → Update `ticket_order.status` = `confirmed`, generate `tickets`
5. **Send webhook** → Include event/ticket data in webhook payload

### Modified Payment Webhook Payload
```json
{
  "event": "payment.approved",
  "transaction_id": "TXN-123",
  "status": "approved",
  "amount": 5000.00,
  "payer_name": "John Doe",
  // ... existing fields ...
  
  // NEW: Event/Ticket fields (if payment is for tickets)
  "order_type": "ticket",
  "ticket_order": {
    "order_number": "TKT-20260127-ABC123",
    "event_id": 1,
    "event_title": "Tech Conference 2026",
    "tickets": [
      {
        "ticket_number": "TKT-20260127-ABC123-001",
        "ticket_type": "VIP",
        "attendee_name": "John Doe",
        "attendee_email": "john@example.com",
        "qr_code": "https://check-outnow.com/tickets/qr/TKT-20260127-ABC123-001"
      }
    ]
  }
}
```

---

## 3. Business Owner Features (Dashboard)

### Event Management
- **Create Event**: Form with all event details, image upload, ticket types
- **Edit Event**: Update event details, manage ticket types
- **Event List**: View all events (draft, published, completed)
- **Event Analytics**: 
  - Total tickets sold
  - Revenue breakdown
  - Ticket type sales
  - Check-in statistics
  - Attendee list export

### Ticket Management
- **Create Ticket Types**: Multiple tiers per event
- **Manage Inventory**: Set quantities, prices, sales windows
- **View Orders**: List all ticket orders
- **Issue Refunds**: Process refunds (links to payment system)
- **Export Attendees**: CSV/Excel export

### Check-in Management
- **Check-in Interface**: QR scanner or manual entry
- **Check-in Reports**: Real-time check-in status

---

## 4. Admin Features

### Event Oversight
- **Approve Events**: Review before publishing (optional)
- **View All Events**: Across all businesses
- **Event Analytics**: Platform-wide statistics
- **Manage Events**: Edit/delete any event
- **Refund Management**: Process refunds

### Business Management
- **Event Revenue**: Track event revenue per business
- **Commission Settings**: Set platform fees for events
- **Reports**: Event performance reports

---

## 5. Public Frontend Features

### Event Discovery
- **Event Listing Page**: `/events`
  - Featured events
  - Upcoming events
  - Search/filter (date, location, category)
  - Category tags

- **Event Detail Page**: `/events/{slug}`
  - Event information
  - Ticket selection
  - Ticket type comparison
  - Event location map
  - Social sharing

### Ticket Purchase Flow
1. **Select Tickets**: Choose ticket types and quantities
2. **Attendee Information**: Enter attendee details (name, email)
3. **Review Order**: Summary before payment
4. **Payment**: Redirect to existing payment gateway
5. **Confirmation**: Display ticket details, QR codes, download PDF

### Ticket Management (Customer)
- **My Tickets**: `/my-tickets` (requires auth or email lookup)
- **View Ticket**: QR code, event details
- **Transfer Ticket**: If enabled by organizer
- **Download PDF**: Ticket receipt

---

## 6. API Endpoints

### Business API (Existing API Key Auth)
```
POST   /api/v1/events                    - Create event
GET    /api/v1/events                    - List business events
GET    /api/v1/events/{id}                - Get event details
PUT    /api/v1/events/{id}                - Update event
DELETE /api/v1/events/{id}                - Delete event

POST   /api/v1/events/{id}/ticket-types  - Create ticket type
GET    /api/v1/events/{id}/ticket-types  - List ticket types
PUT    /api/v1/ticket-types/{id}         - Update ticket type

GET    /api/v1/events/{id}/orders        - Get event orders
GET    /api/v1/ticket-orders/{id}        - Get order details
POST   /api/v1/ticket-orders/{id}/refund - Issue refund
```

### Public API
```
GET    /api/public/events                - List published events
GET    /api/public/events/{slug}         - Get event details
POST   /api/public/events/{id}/orders    - Create ticket order
GET    /api/public/tickets/{ticket_number} - Verify ticket (for check-in)
```

---

## 7. File Structure

```
app/
├── Models/
│   ├── Event.php
│   ├── TicketType.php
│   ├── TicketOrder.php
│   ├── TicketOrderItem.php
│   ├── Ticket.php
│   └── EventCheckIn.php
├── Http/Controllers/
│   ├── Business/
│   │   ├── EventController.php
│   │   ├── TicketTypeController.php
│   │   ├── TicketOrderController.php
│   │   └── EventCheckInController.php
│   ├── Admin/
│   │   └── EventManagementController.php
│   └── Public/
│       ├── EventController.php
│       └── TicketController.php
├── Services/
│   ├── EventService.php
│   ├── TicketService.php
│   ├── QRCodeService.php
│   └── TicketPDFService.php
└── Jobs/
    ├── GenerateTicketsJob.php
    ├── SendTicketEmailJob.php
    └── EventReminderJob.php

database/migrations/
├── 2026_01_27_000001_create_events_table.php
├── 2026_01_27_000002_create_ticket_types_table.php
├── 2026_01_27_000003_create_ticket_orders_table.php
├── 2026_01_27_000004_create_ticket_order_items_table.php
├── 2026_01_27_000005_create_tickets_table.php
└── 2026_01_27_000006_create_event_check_ins_table.php

resources/views/
├── business/
│   ├── events/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php
│   └── ticket-orders/
│       └── index.blade.php
├── admin/
│   └── events/
│       └── index.blade.php
└── public/
    ├── events/
    │   ├── index.blade.php
    │   └── show.blade.php
    └── tickets/
        ├── my-tickets.blade.php
        └── show.blade.php
```

---

## 8. Key Features & Workflows

### Event Creation Workflow
1. Business owner creates event → Status: `draft`
2. Adds ticket types with prices/quantities
3. Publishes event → Status: `published`
4. Event appears on public listing

### Ticket Purchase Workflow
1. Customer browses events → Selects event
2. Chooses ticket types/quantities
3. Enters attendee information
4. Creates order → Status: `pending`
5. Payment processed via existing gateway
6. Payment approved → Order confirmed
7. Tickets generated → QR codes created
8. Email sent with tickets (PDF + QR codes)

### Check-in Workflow
1. Attendee arrives at event
2. Organizer scans QR code or enters ticket number
3. System validates ticket
4. Marks as checked in
5. Updates event statistics

---

## 9. Integration Points

### With Existing Payment System
- **Reuse Payment Model**: Link `ticket_order.payment_id` to `payments.id`
- **Reuse Payment API**: Use existing `/api/v1/payment-request` endpoint
- **Extend Webhook**: Add ticket data to webhook payload
- **Reuse Account Assignment**: Use existing account number assignment

### With Business System
- **Business Ownership**: Events belong to businesses
- **Revenue Tracking**: Event revenue adds to business balance
- **Commission**: Apply existing charge structure to ticket sales

---

## 10. Security Considerations

- **QR Code Security**: Unique, non-guessable ticket numbers
- **Ticket Validation**: Verify ticket before check-in
- **Rate Limiting**: Prevent ticket scalping
- **Access Control**: Business owners can only manage their events
- **Payment Security**: Reuse existing secure payment flow

---

## 11. Next Steps

1. **Phase 1**: Database migrations + Models
2. **Phase 2**: Business owner event management (CRUD)
3. **Phase 3**: Public event listing & detail pages
4. **Phase 4**: Ticket purchase flow + Payment integration
5. **Phase 5**: Ticket generation + Email delivery
6. **Phase 6**: Check-in system
7. **Phase 7**: Admin panel + Analytics
8. **Phase 8**: API endpoints

---

## Questions to Consider

1. **Event Categories**: Do we need categories/tags for events?
2. **Recurring Events**: Support for multi-day or recurring events?
3. **Waitlist**: Automatic waitlist when sold out?
4. **Promo Codes**: Discount codes/coupons?
5. **Affiliate System**: Referral commissions for ticket sales?
6. **Event Reminders**: Email/SMS reminders before event?
7. **Mobile App**: Native app for check-in or web-based only?
