"""
Payment Email Extraction Service (FastAPI)
Extracts payment information from bank email notifications.
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any
import re
import logging
from bs4 import BeautifulSoup
import json

app = FastAPI(title="Payment Email Extractor", version="1.0.0")

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Known bank templates
BANK_TEMPLATES = {
    "gtbank.com": {
        "name": "GTBank",
        "amount_patterns": [
            r'<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]*</td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*</td>',
            r'<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]+(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*</td>',
            r'(?:amount|sum|value|total|paid|payment)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        ],
        "name_patterns": [
            r'<td[^>]*>[\s]*(?:description|remarks)[\s:]*</td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to',
            r'from\s+([A-Z][A-Z\s]+?)\s+to',
        ],
    },
    "accessbank.com": {
        "name": "Access Bank",
        "amount_patterns": [
            r'(?:amount|sum)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        ],
        "name_patterns": [
            r'from\s+([A-Z][A-Z\s]+?)',
        ],
    },
}

# Currency codes
CURRENCIES = {
    "NGN": ["ngn", "naira", "₦", "nigeria naira"],
    "USD": ["usd", "dollar", "$", "us dollar"],
    "GBP": ["gbp", "pound", "£", "british pound"],
    "EUR": ["eur", "euro", "€"],
}

# Word to number conversion (Nigerian format)
WORD_TO_NUMBER = {
    "one": 1, "two": 2, "three": 3, "four": 4, "five": 5,
    "six": 6, "seven": 7, "eight": 8, "nine": 9, "ten": 10,
    "eleven": 11, "twelve": 12, "thirteen": 13, "fourteen": 14, "fifteen": 15,
    "sixteen": 16, "seventeen": 17, "eighteen": 18, "nineteen": 19,
    "twenty": 20, "thirty": 30, "forty": 40, "fifty": 50,
    "sixty": 60, "seventy": 70, "eighty": 80, "ninety": 90,
    "hundred": 100, "thousand": 1000, "million": 1000000, "billion": 1000000000,
}


class ExtractionRequest(BaseModel):
    email_id: int
    subject: str
    from_email: str
    text_body: Optional[str] = None
    html_body: Optional[str] = None
    email_date: Optional[str] = None


class ExtractionResult(BaseModel):
    amount: float = Field(..., description="Extracted amount")
    currency: str = Field(default="NGN", description="Currency code")
    direction: str = Field(default="credit", description="credit or debit")
    confidence: float = Field(..., ge=0.0, le=1.0, description="Confidence score 0-1")
    source: str = Field(..., description="Extraction source: html_table, html_text, text_body, template, etc.")
    sender_name: Optional[str] = Field(None, description="Extracted sender name")
    account_number: Optional[str] = Field(None, description="Extracted account number")


class ExtractionResponse(BaseModel):
    success: bool
    data: Optional[ExtractionResult] = None
    errors: List[str] = Field(default_factory=list)
    diagnostics: Optional[Dict[str, Any]] = None


def extract_from_html_table(html: str) -> Optional[Dict[str, Any]]:
    """Extract amount from HTML table structures (most accurate)."""
    if not html:
        return None
    
    soup = BeautifulSoup(html, 'html.parser')
    
    # Look for table cells containing amount
    for td in soup.find_all('td'):
        text = td.get_text(strip=True).lower()
        
        # Check if this cell indicates it contains an amount
        if any(keyword in text for keyword in ['amount', 'sum', 'value', 'total', 'paid', 'payment']):
            # Look for amount in this cell or next sibling
            amount_text = text
            
            # Check current cell for amount
            amount_match = re.search(r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)', text, re.IGNORECASE)
            if amount_match:
                try:
                    amount = float(amount_match.group(1).replace(',', ''))
                    if amount >= 10:
                        return {
                            "amount": amount,
                            "currency": "NGN",
                            "confidence": 0.95,
                            "source": "html_table",
                        }
                except ValueError:
                    pass
            
            # Check next sibling cell
            next_td = td.find_next_sibling('td')
            if next_td:
                next_text = next_td.get_text(strip=True)
                amount_match = re.search(r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)', next_text, re.IGNORECASE)
                if amount_match:
                    try:
                        amount = float(amount_match.group(1).replace(',', ''))
                        if amount >= 10:
                            return {
                                "amount": amount,
                                "currency": "NGN",
                                "confidence": 0.95,
                                "source": "html_table",
                            }
                    except ValueError:
                        pass
    
    # Try finding any table cell with NGN/Naira amount
    for td in soup.find_all('td'):
        text = td.get_text(strip=True)
        amount_match = re.search(r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)', text, re.IGNORECASE)
        if amount_match:
            try:
                amount = float(amount_match.group(1).replace(',', ''))
                if amount >= 10:
                    return {
                        "amount": amount,
                        "currency": "NGN",
                        "confidence": 0.90,
                        "source": "html_table",
                    }
            except ValueError:
                pass
    
    return None


def extract_from_html_text(html: str) -> Optional[Dict[str, Any]]:
    """Extract amount from HTML text (not table-based)."""
    if not html:
        return None
    
    patterns = [
        r'(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'([\d,]+\.?\d*)\s*(?:naira|ngn)',
    ]
    
    for pattern in patterns:
        matches = re.finditer(pattern, html, re.IGNORECASE)
        for match in matches:
            try:
                amount = float(match.group(1).replace(',', ''))
                if amount >= 10:
                    return {
                        "amount": amount,
                        "currency": "NGN",
                        "confidence": 0.85,
                        "source": "html_text",
                    }
            except (ValueError, IndexError):
                continue
    
    return None


def extract_from_text_body(text: str) -> Optional[Dict[str, Any]]:
    """Extract amount from plain text body."""
    if not text:
        return None
    
    patterns = [
        r'(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'([\d,]+\.?\d*)\s*(?:naira|ngn|usd|dollar)',
    ]
    
    for pattern in patterns:
        matches = re.finditer(pattern, text, re.IGNORECASE)
        for match in matches:
            try:
                amount = float(match.group(1).replace(',', ''))
                if amount >= 10:
                    return {
                        "amount": amount,
                        "currency": "NGN",
                        "confidence": 0.80,
                        "source": "text_body",
                    }
            except (ValueError, IndexError):
                continue
    
    return None


def extract_sender_name(html: str, text: str) -> Optional[str]:
    """Extract sender name from email content."""
    # Try HTML first
    if html:
        soup = BeautifulSoup(html, 'html.parser')
        patterns = [
            r'<td[^>]*>[\s]*(?:description|remarks)[\s:]*</td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)\s+to',
            r'from\s+([A-Z][A-Z\s]+?)\s+to',
        ]
        
        for pattern in patterns:
            match = re.search(pattern, html, re.IGNORECASE)
            if match:
                name = match.group(1).strip().lower()
                if len(name) >= 3:
                    return name
    
    # Try text
    if text:
        match = re.search(r'from\s+([A-Z][A-Z\s]+?)\s+to', text, re.IGNORECASE)
        if match:
            name = match.group(1).strip().lower()
            if len(name) >= 3:
                return name
    
    return None


def detect_currency(text: str, html: str) -> str:
    """Detect currency from email content."""
    content = (text or "") + " " + (html or "")
    content_lower = content.lower()
    
    for currency, keywords in CURRENCIES.items():
        if any(keyword in content_lower for keyword in keywords):
            return currency
    
    return "NGN"  # Default to Naira


@app.post("/extract", response_model=ExtractionResponse)
async def extract_payment_info(request: ExtractionRequest):
    """
    Extract payment information from email content.
    
    Returns structured payment data with confidence scoring.
    """
    diagnostics = {
        "steps": [],
        "errors": [],
        "text_length": len(request.text_body or ""),
        "html_length": len(request.html_body or ""),
    }
    
    try:
        # Check for bank template match
        bank_template = None
        for domain, template in BANK_TEMPLATES.items():
            if domain in request.from_email.lower():
                bank_template = template
                diagnostics["steps"].append(f"Template found: {template['name']}")
                break
        
        if not bank_template:
            diagnostics["steps"].append("No matching bank template found")
        
        # Strategy 1: Try HTML table extraction (highest confidence)
        if request.html_body:
            diagnostics["steps"].append("Attempting HTML table extraction")
            result = extract_from_html_table(request.html_body)
            if result:
                result["sender_name"] = extract_sender_name(request.html_body, request.text_body)
                result["currency"] = detect_currency(request.text_body, request.html_body)
                diagnostics["steps"].append(f"Extraction successful: {result['source']}")
                return ExtractionResponse(
                    success=True,
                    data=ExtractionResult(**result),
                    diagnostics=diagnostics,
                )
            else:
                diagnostics["errors"].append("HTML table extraction failed")
        
        # Strategy 2: Try HTML text extraction
        if request.html_body:
            diagnostics["steps"].append("Attempting HTML text extraction")
            result = extract_from_html_text(request.html_body)
            if result:
                result["sender_name"] = extract_sender_name(request.html_body, request.text_body)
                result["currency"] = detect_currency(request.text_body, request.html_body)
                diagnostics["steps"].append(f"Extraction successful: {result['source']}")
                return ExtractionResponse(
                    success=True,
                    data=ExtractionResult(**result),
                    diagnostics=diagnostics,
                )
            else:
                diagnostics["errors"].append("HTML text extraction failed")
        
        # Strategy 3: Try text body extraction
        if request.text_body:
            diagnostics["steps"].append("Attempting text body extraction")
            result = extract_from_text_body(request.text_body)
            if result:
                result["sender_name"] = extract_sender_name(request.html_body, request.text_body)
                result["currency"] = detect_currency(request.text_body, request.html_body)
                diagnostics["steps"].append(f"Extraction successful: {result['source']}")
                return ExtractionResponse(
                    success=True,
                    data=ExtractionResult(**result),
                    diagnostics=diagnostics,
                )
            else:
                diagnostics["errors"].append("Text body extraction failed")
        
        # Strategy 4: Convert HTML to text and try again
        if request.html_body:
            from bs4 import BeautifulSoup
            soup = BeautifulSoup(request.html_body, 'html.parser')
            rendered_text = soup.get_text(separator=' ', strip=True)
            
            if rendered_text:
                diagnostics["steps"].append("Attempting HTML-to-text conversion extraction")
                result = extract_from_text_body(rendered_text)
                if result:
                    result["sender_name"] = extract_sender_name(request.html_body, request.text_body)
                    result["currency"] = detect_currency(request.text_body, request.html_body)
                    result["source"] = "html_rendered_text"
                    result["confidence"] = 0.75  # Lower confidence for converted text
                    diagnostics["steps"].append(f"Extraction successful: {result['source']}")
                    return ExtractionResponse(
                        success=True,
                        data=ExtractionResult(**result),
                        diagnostics=diagnostics,
                    )
                else:
                    diagnostics["errors"].append("HTML-to-text extraction failed")
        
        # All strategies failed
        diagnostics["errors"].append("All extraction strategies failed")
        
        return ExtractionResponse(
            success=False,
            errors=diagnostics["errors"],
            diagnostics=diagnostics,
        )
    
    except Exception as e:
        logger.error(f"Extraction error: {str(e)}", exc_info=True)
        diagnostics["errors"].append(f"Extraction exception: {str(e)}")
        return ExtractionResponse(
            success=False,
            errors=diagnostics["errors"],
            diagnostics=diagnostics,
        )


@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy", "service": "payment-email-extractor"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
