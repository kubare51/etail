"""
Scrubber — Remove PII, credentials, and sensitive data from training examples.

Three-layer approach ported from db-tunnel MCP's proven PII masking system:
  Layer 1: REDACT — exact column names containing secrets → "********"
  Layer 2: PII COLUMN PATTERNS — partial masking for known PII column names
  Layer 3: VALUE SCANNING — detect inline PII in any text (emails, phones, IDs)

Plus additional patterns for training data context (file paths, connection strings,
SSH keys, API keys, etc.)
"""
from __future__ import annotations
import re
from .models import TrainingExample


# ═══════════════════════════════════════════════════════════════════════════════
# Layer 1: REDACT — secrets get fully hidden
# Ported from db-tunnel MCP mcpTools.js REDACT_COLUMNS
# ═══════════════════════════════════════════════════════════════════════════════

REDACT_KEYWORDS = {
    # Passwords & auth
    'password', 'passwd', 'pass', 'password_hash', 'pwd_hash',
    'mail_password', 'gsp_password', 'einvpwd', 'ej_pwd',
    'secret', 'form_secret', 'webhook_secret',
    'token', 'authtoken', 'authtokenrequest', 'securitytoken', 'token_id',
    'access_token', 'refresh_token', 'private_key',
    'api_key', 'apikey', 'one_signal_auth_key', 'zoop_api_key',
    'khimji_auth', 'khimji_x_key', 'msg91_authkey',
    'otp', 'verified_otp',
    # Card/financial secrets
    'cvv', 'credit_card', 'card_number', 'card_no', 'card_last4',
    'ssn', 'security_answer', 'pin',
    # SMTP
    'smtp_pass', 'smtp_user',
}

# ═══════════════════════════════════════════════════════════════════════════════
# Layer 2: VALUE-BASED PATTERNS — detect and mask specific PII formats in text
# Ported from db-tunnel MCP maskValue() + scanAndMaskValue()
# ═══════════════════════════════════════════════════════════════════════════════

# Email: john.doe@example.com → j***@***.com
EMAIL_PATTERN = re.compile(
    r'[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}'
)

# Indian mobile: 9876543210 → 98****10
INDIAN_MOBILE_PATTERN = re.compile(
    r'(?<!\d)(\+91[\s\-]?)?[6-9]\d{9}(?!\d)'
)

# Aadhaar: 1234 5678 9012 → XXXX-XXXX-9012
AADHAAR_PATTERN = re.compile(
    r'\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b'
)

# PAN: ABCDE1234F → AB***4F
PAN_PATTERN = re.compile(
    r'\b[A-Z]{5}\d{4}[A-Z]\b'
)

# GST: 22AABCU1234F1Z5 → 22*****Z5
GST_PATTERN = re.compile(
    r'\b\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z\d][A-Z\d]\b'
)

# IP Address: 192.168.1.100 → 192.168.***
IP_PATTERN = re.compile(
    r'\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b'
)

# ═══════════════════════════════════════════════════════════════════════════════
# Layer 3: TRAINING-DATA SPECIFIC — patterns unique to conversation transcripts
# ═══════════════════════════════════════════════════════════════════════════════

# Database credentials in code: password = 'secret123'
DB_CRED_PATTERN = re.compile(
    r"(?:password|passwd|pwd|db_pass)\s*[:=]\s*['\"]?[\w!@#$%^&*()]+['\"]?",
    re.IGNORECASE
)

# API keys / bearer tokens
API_KEY_PATTERN = re.compile(
    r"(?:api[_\-]?key|bearer)\s*[:=]\s*['\"]?[\w\-\.]+['\"]?",
    re.IGNORECASE
)

# AWS keys
AWS_KEY_PATTERN = re.compile(r'(?:AKIA|ASIA)[A-Z0-9]{16}')

# GitHub tokens
GITHUB_TOKEN_PATTERN = re.compile(r'gh[ps]_[A-Za-z0-9_]{36,}')

# Connection strings
CONN_STRING_PATTERN = re.compile(
    r'(?:mysql|postgres|mongodb|redis)://\S+', re.IGNORECASE
)

# SSH private keys
SSH_KEY_PATTERN = re.compile(
    r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----[\s\S]*?'
    r'-----END (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'
)

# Windows user paths: C:\Users\admin\ → C:\Users\***\
WIN_USER_PATH = re.compile(r'C:\\Users\\[^\\]+\\', re.IGNORECASE)

# Linux user paths: /home/user/ → /home/***/
LINUX_USER_PATH = re.compile(r'/home/[^/]+/')


# ═══════════════════════════════════════════════════════════════════════════════
# Masking functions (ported from db-tunnel MCP)
# ═══════════════════════════════════════════════════════════════════════════════

def _mask_email(match: re.Match) -> str:
    """john.doe@example.com → j***@***.com"""
    email = match.group(0)
    parts = email.split('@')
    if len(parts) != 2:
        return '***@***.***'
    local, domain = parts
    tld = domain.split('.')[-1] if '.' in domain else domain
    first = local[0] if local else '*'
    return f'{first}***@***.{tld}'


def _mask_indian_mobile(match: re.Match) -> str:
    """9876543210 → 98****10"""
    phone = match.group(0)
    digits = re.sub(r'\D', '', phone)
    # Skip country code if present
    local = digits[-10:] if len(digits) > 10 else digits
    if len(local) >= 4:
        return local[:2] + '****' + local[-2:]
    return '****'


def _mask_aadhaar(match: re.Match) -> str:
    """1234 5678 9012 → XXXX-XXXX-9012"""
    digits = re.sub(r'\D', '', match.group(0))
    return 'XXXX-XXXX-' + digits[-4:]


def _mask_pan(match: re.Match) -> str:
    """ABCDE1234F → AB***4F"""
    pan = match.group(0)
    return pan[:2] + '***' + pan[-2:]


def _mask_gst(match: re.Match) -> str:
    """22AABCU1234F1Z5 → 22*****Z5"""
    gst = match.group(0)
    return gst[:2] + '*****' + gst[-3:]


def _mask_ip(match: re.Match) -> str:
    """192.168.1.100 → 192.168.***"""
    ip = match.group(0)
    parts = ip.split('.')
    return '.'.join(parts[:2]) + '.***'


# ═══════════════════════════════════════════════════════════════════════════════
# Main scrubbing function
# ═══════════════════════════════════════════════════════════════════════════════

def scrub_text(text: str) -> str:
    """
    Apply all 3 layers of PII scrubbing to a text string.

    Layer 1: Redact credential patterns in code/config
    Layer 2: Mask structured PII (email, phone, PAN, Aadhaar, GST, IP)
    Layer 3: Remove training-data specific secrets (AWS keys, SSH keys, etc.)
    """
    if not text:
        return text

    result = text

    # Layer 1: Redact credential assignments in code
    result = DB_CRED_PATTERN.sub('password=***REDACTED***', result)
    result = API_KEY_PATTERN.sub('api_key=***REDACTED***', result)

    # Layer 2: Mask structured PII values (order matters — specific before generic)
    result = SSH_KEY_PATTERN.sub('***SSH_KEY_REDACTED***', result)
    result = AWS_KEY_PATTERN.sub('***AWS_KEY_REDACTED***', result)
    result = GITHUB_TOKEN_PATTERN.sub('***GITHUB_TOKEN_REDACTED***', result)
    result = CONN_STRING_PATTERN.sub('***CONN_STRING_REDACTED***', result)

    # Structured Indian PII (from db-tunnel MCP)
    result = AADHAAR_PATTERN.sub(lambda m: _mask_aadhaar(m), result)
    result = PAN_PATTERN.sub(lambda m: _mask_pan(m), result)
    result = GST_PATTERN.sub(lambda m: _mask_gst(m), result)
    result = EMAIL_PATTERN.sub(lambda m: _mask_email(m), result)
    result = INDIAN_MOBILE_PATTERN.sub(lambda m: _mask_indian_mobile(m), result)
    result = IP_PATTERN.sub(lambda m: _mask_ip(m), result)

    # Layer 3: File paths with usernames
    result = WIN_USER_PATH.sub(r'C:\\Users\\***\\', result)
    result = LINUX_USER_PATH.sub('/home/***/', result)

    return result


def scrub_example(example: TrainingExample) -> TrainingExample:
    """Scrub PII from all fields of a training example."""
    example.instruction = scrub_text(example.instruction)
    example.input_context = scrub_text(example.input_context)
    example.output = scrub_text(example.output)

    if example.git_diff:
        example.git_diff = scrub_text(example.git_diff)
    if example.before_code:
        example.before_code = scrub_text(example.before_code)
    if example.after_code:
        example.after_code = scrub_text(example.after_code)

    # Scrub file paths
    example.files_modified = [
        scrub_text(f) for f in example.files_modified
    ]

    return example


# Paths that might contain sensitive info
SENSITIVE_PATH_PATTERNS = [
    re.compile(r'\.env$', re.IGNORECASE),
    re.compile(r'config\.local', re.IGNORECASE),
    re.compile(r'secrets?\.', re.IGNORECASE),
    re.compile(r'credentials', re.IGNORECASE),
]


def has_sensitive_files(example: TrainingExample) -> bool:
    """Check if any files in the example are sensitive config files."""
    for f in example.files_modified:
        for pat in SENSITIVE_PATH_PATTERNS:
            if pat.search(f):
                return True
    return False


def scrub_batch(examples: list[TrainingExample]) -> list[TrainingExample]:
    """Scrub a batch of examples, flagging sensitive ones."""
    results = []
    for ex in examples:
        if has_sensitive_files(ex):
            ex.tags.append('contains_sensitive_file')
            ex.quality = 'skip'
        ex = scrub_example(ex)
        results.append(ex)
    return results
