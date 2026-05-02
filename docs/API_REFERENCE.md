# API Reference

Backend APIs for exam system integration.

---

## Authentication

All requests use session-based authentication. Must be logged in via `/admin/login.php` or `/student/login.php`.

**CSRF Protection**: Include CSRF token in POST requests.

```php
// Get token
$token = $_SESSION['csrf_token'];

// Include in form
<input type="hidden" name="csrf_token" value="<?= $token ?>">
```

---

## Endpoints

### 1. Save Answer (AJAX)

**POST** `/api/save-answer.php`

Save student answer during exam.

**Parameters:**
```json
{
  "attempt_id": 123,
  "question_id": 456,
  "selected": [1, 3],           // For MCQ/Multi-select
  "bool": true,                 // For True/False
  "text": "answer text",        // For Short Answer
  "numeric": 42.5               // For Numeric
}
```

**Response (Success):**
```json
{
  "status": "ok",
  "saved": true,
  "attempt_id": 123,
  "question_id": 456
}
```

**Response (Error):**
```json
{
  "status": "error",
  "message": "Invalid attempt or question"
}
```

**Example:**
```javascript
fetch('/api/save-answer.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    attempt_id: 123,
    question_id: 456,
    selected: [2]  // MCQ answer: option 2
  })
})
.then(r => r.json())
.then(data => console.log(data));
```

---

### 2. Violation Tracking

**POST** `/api/violation.php`

Track exam lockdown violations.

**Parameters:**
```json
{
  "attempt_id": 123,
  "event_type": "tab_switch",
  "description": "Switched tab to browser"
}
```

**Event Types:**
- `tab_switch` - User switched to another tab
- `screenshot` - Screenshot attempt detected
- `window_blur` - Window lost focus
- `minimize` - Window minimized
- `right_click` - Right-click attempted

**Response:**
```json
{
  "status": "recorded",
  "violation_id": 789
}
```

**Example:**
```javascript
// Track tab switch
fetch('/api/violation.php', {
  method: 'POST',
  body: JSON.stringify({
    attempt_id: 123,
    event_type: 'tab_switch',
    description: 'User switched to another application'
  }),
  headers: {'Content-Type': 'application/json'}
});
```

---

### 3. QR Lookup

**GET** `/api/qr-lookup.php`

Verify student from QR code data.

**Parameters:**
```
GET /api/qr-lookup.php?data=BEL-KOTDWAR|BEL-KOT-001|123
```

**Data Format:**
```
{exam}|{roll_number}|{student_id}
```

**Response (Success):**
```json
{
  "status": "verified",
  "student": {
    "id": 123,
    "name": "Rajesh Kumar",
    "roll_number": "BEL-KOT-001",
    "email": "rajesh@mail.com",
    "dob": "1999-05-15",
    "category": "internal",
    "photo": "/uploads/photos/123.jpg"
  }
}
```

**Response (Error):**
```json
{
  "status": "not_found",
  "message": "Student not found in database"
}
```

---

## Admin APIs (Internal)

### Bulk Upload Students

**POST** `/admin/students.php`

**Form Data:**
```
action=bulk
csv=name,email,roll_number,dob,category,password
csv=Rajesh,rajesh@mail.com,BEL-001,1999-05-15,internal,Pass@123
```

**Response:**
```json
{
  "status": "success",
  "imported": 5,
  "skipped": 1,
  "errors": []
}
```

### Bulk Upload Questions

**POST** `/admin/questions.php`

**Form Data:**
```
action=bulk_import
exam_id=1
csv=question_type,question,option1,option2,option3,option4,correct,marks,negative
csv=mcq,Capital of India?,Delhi,Mumbai,Bangalore,Chennai,1,1,0.25
```

**Response:**
```json
{
  "status": "success",
  "imported": 10,
  "skipped": 0,
  "errors": []
}
```

---

## Export APIs

### Export Exam Results

**POST** `/admin/export-results.php`

Export attempt results to CSV.

**Parameters:**
```
exam_id=1        // Optional: specific exam
attempt_id=123   // Optional: specific attempt
```

**Response:** CSV file download

---

## Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | Success | Request completed |
| 400 | Bad Request | Check parameters |
| 401 | Unauthorized | Login required |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource doesn't exist |
| 500 | Server Error | Contact admin |

---

## Rate Limiting

No rate limiting implemented. For production, add:

```php
// includes/config.php
define('RATE_LIMIT', '100/minute');  // Requests per minute
```

---

## CORS (Cross-Origin)

CORS is disabled. All APIs must be called from same domain.

For cross-domain access, configure in Apache:

```apache
<VirtualHost *:80>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
</VirtualHost>
```

---

## Authentication Headers

For API clients (optional):

```
Authorization: Bearer {session_token}
X-CSRF-Token: {csrf_token}
Content-Type: application/json
```

---

## Example Implementations

### JavaScript Fetch

```javascript
// Save answer
async function saveAnswer(attemptId, questionId, answer) {
  const response = await fetch('/api/save-answer.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({
      attempt_id: attemptId,
      question_id: questionId,
      selected: answer
    })
  });
  return await response.json();
}
```

### PHP cURL

```php
// Lookup student via QR
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => 'http://localhost/api/qr-lookup.php?data=BEL-KOTDWAR|BEL-001|123',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_COOKIE => 'PHPSESSID=' . session_id()
]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
```

---

## Webhooks

Not currently implemented. For integration with external systems, contact admin.

---

## SDK (Coming Soon)

- JavaScript SDK
- PHP SDK
- Python SDK

---

## Support

For API issues:
1. Check error message and code
2. Review examples above
3. Test with [Testing Guide](TESTING.md)
4. Contact admin with details
