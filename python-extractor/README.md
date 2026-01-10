# Payment Email Extraction Service

FastAPI microservice for extracting payment information from bank email notifications.

## Features

- HTML table extraction (highest accuracy)
- HTML text extraction
- Plain text extraction
- Currency detection (NGN, USD, GBP, EUR)
- Sender name extraction
- Confidence scoring
- Bank template matching
- Comprehensive diagnostics

## Installation

```bash
# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

## Running

```bash
# Development
python main.py

# Production with uvicorn
uvicorn main:app --host 0.0.0.0 --port 8000 --workers 4
```

## API

### POST /extract

Extract payment information from email content.

**Request:**
```json
{
  "email_id": 12345,
  "subject": "Credit Alert",
  "from_email": "noreply@gtbank.com",
  "text_body": "...",
  "html_body": "...",
  "email_date": "2024-01-10T10:30:00Z"
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "amount": 1000.00,
    "currency": "NGN",
    "direction": "credit",
    "confidence": 0.95,
    "source": "html_table",
    "sender_name": "john doe",
    "account_number": null
  },
  "errors": [],
  "diagnostics": {
    "steps": ["Attempting HTML table extraction", "Extraction successful: html_table"],
    "errors": [],
    "text_length": 234,
    "html_length": 5432
  }
}
```

**Response (Failure):**
```json
{
  "success": false,
  "data": null,
  "errors": ["All extraction strategies failed"],
  "diagnostics": {
    "steps": ["Attempting HTML table extraction", "Attempting HTML text extraction"],
    "errors": ["HTML table extraction failed", "HTML text extraction failed"],
    "text_length": 0,
    "html_length": 0
  }
}
```

### GET /health

Health check endpoint.

## Integration with Laravel

This service is called from Laravel's `PaymentMatchingService` via HTTP.

Laravel sends email content, Python extracts payment info, Laravel validates and matches.
