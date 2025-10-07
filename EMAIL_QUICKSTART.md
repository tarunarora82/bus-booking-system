# 🚀 Email Notification System - Quick Start Guide

## Overview

The Bus Booking System now includes automatic email notifications for booking confirmations and cancellations. Emails are sent to employees based on their email address in the employee list.

## 📋 Prerequisites

- PHP 7.4 or higher
- Access to Intel SMTP server (smtpauth.intel.com:587)
- Email credentials configured
- Running bus booking system

## ⚡ Quick Setup (5 Minutes)

### Step 1: Verify Configuration

The email system is pre-configured with Intel SMTP settings. Check the configuration:

**File**: `backend/config/email.php`

```php
SMTP_HOST = 'smtpauth.intel.com'
SMTP_PORT = 587
SMTP_USERNAME = 'sys_github01@intel.com'
SMTP_PASSWORD = 'dateAug21st2025!@#$%'
```

### Step 2: Start the Backend

```powershell
cd backend
php -S localhost:3000
```

### Step 3: Run Tests

**Option A: Standalone Test**
```powershell
php test-email-system.php
```

**Option B: PowerShell Test Script**
```powershell
.\test-email-notifications.ps1
```

**Option C: Interactive Web UI**
1. Open `test-email-notifications.html` in your browser
2. Test various scenarios with the interactive interface

## 🎯 How to Use

### In Your Application

Email notifications are **automatically sent** when:

1. **Creating a Booking**
```javascript
// API Call
fetch('http://localhost:3000/booking/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        employee_id: '11453732',
        bus_number: 'BUS001',
        schedule_date: '2025-10-07'
    })
})
```

**Response includes**:
```json
{
    "status": "success",
    "email_sent": true,
    "email_notification": "Confirmation email sent to john.doe@intel.com"
}
```

2. **Cancelling a Booking**
```javascript
// API Call
fetch('http://localhost:3000/booking/cancel', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        employee_id: '11453732',
        bus_number: 'BUS001'
    })
})
```

**Response includes**:
```json
{
    "status": "success",
    "email_sent": true,
    "email_notification": "Cancellation email sent to john.doe@intel.com"
}
```

## 📧 Email Content

### Booking Confirmation Email

**Subject**: Bus Booking Confirmation - {BOOKING_ID}

**Includes**:
- ✅ Unique Booking ID (e.g., BK202510070001)
- 👤 Employee Name & ID
- 🚌 Bus Number
- 📍 Route Details
- 📅 Schedule Date
- 🕐 Departure Time
- 🌅 Slot (Morning/Evening/Night)
- ⚠️ Important Guidelines
- 🔔 Departure Timeline

### Cancellation Email

**Subject**: Bus Booking Cancellation - {BOOKING_ID}

**Includes**:
- ❌ Cancellation Status
- 🎫 Booking ID
- 👤 Employee Details
- 🚌 Bus & Route Information
- 📅 Original Schedule
- ℹ️ Rebooking Information

## 🛡️ Error Handling

### Employee Without Email

✅ **Booking succeeds**  
❌ **Email is NOT sent**  
📝 **Log entry created**

```json
{
    "status": "success",
    "email_sent": false,
    "email_skip_reason": "missing_email",
    "email_notification": "Email not sent - No email address available"
}
```

### Invalid Email Format

✅ **Booking succeeds**  
❌ **Email is NOT sent**  
📝 **Log entry created**

```json
{
    "status": "success",
    "email_sent": false,
    "email_skip_reason": "invalid_email",
    "email_notification": "Email not sent - Invalid email address"
}
```

## 📊 Monitoring

### View Email Logs

**Via API**:
```bash
curl http://localhost:3000/admin/email-log
```

**Via File**:
```bash
cat /tmp/bus_bookings/email_log.txt
```

**Via Web UI**:
Open `test-email-notifications.html` and click "View Email Log"

### Log Format

```
[2025-10-07 10:30:00] [SUCCESS] Booking confirmation sent to john.doe@intel.com for booking BK202510070001
[2025-10-07 10:31:00] [WARNING] No email address found for employee: NO_EMAIL_EMP
[2025-10-07 10:32:00] [ERROR] Invalid email address for employee: 12345 - invalid@
```

## ✅ Testing Checklist

- [ ] Run standalone test: `php test-email-system.php`
- [ ] Test booking creation with valid email
- [ ] Test booking creation without email
- [ ] Test booking cancellation with valid email
- [ ] Check inbox for test emails
- [ ] Verify email templates render correctly
- [ ] Check email log for activity
- [ ] Test with real employee data

## 🎨 Test Scenarios

### Scenario 1: Happy Path
1. Create booking for employee with valid email
2. Check email inbox for confirmation
3. Verify booking ID in email
4. Cancel booking
5. Check inbox for cancellation email

### Scenario 2: Missing Email
1. Create booking for employee without email
2. Verify booking succeeds
3. Confirm no email sent
4. Check log for warning

### Scenario 3: Invalid Email
1. Add employee with invalid email format
2. Create booking
3. Verify booking succeeds
4. Confirm no email sent
5. Check log for error

## 🔧 Troubleshooting

### Email Not Sending

**Check 1: SMTP Configuration**
```powershell
# Verify configuration in backend/config/email.php
cat backend/config/email.php
```

**Check 2: Employee Email**
```powershell
# Test with known good employee
php test-email-system.php
```

**Check 3: PHP mail() Function**
```powershell
# Check PHP configuration
php -i | grep -i mail
```

**Check 4: Network Connectivity**
```powershell
# Test SMTP connection
Test-NetConnection smtpauth.intel.com -Port 587
```

### Common Issues

| Issue | Solution |
|-------|----------|
| Email goes to spam | Add sender to safe senders list |
| SMTP timeout | Check firewall/proxy settings |
| Authentication failed | Verify credentials in config |
| No email received | Check employee email address |

## 📞 Support

For issues:
1. Check email log: `/tmp/bus_bookings/email_log.txt`
2. Verify employee email in system
3. Test SMTP connectivity
4. Review API response for error details

## 🎓 Advanced Usage

### Custom Email Templates

Edit `backend/EmailService.php`:
- `buildBookingConfirmationEmail()` - Customize confirmation template
- `buildBookingCancellationEmail()` - Customize cancellation template

### SMTP Configuration

Edit `backend/config/email.php`:
- Change SMTP server
- Update credentials
- Modify from address
- Adjust timeout settings

## 📚 Additional Resources

- **Full Documentation**: `EMAIL_SYSTEM_DOCUMENTATION.md`
- **API Documentation**: `README.md`
- **Test Suite**: `test-email-notifications.html`
- **Standalone Test**: `test-email-system.php`

## 🎉 Success Indicators

✅ Emails sent successfully  
✅ Unique booking IDs in all emails  
✅ Complete booking details included  
✅ Error handling works correctly  
✅ Email logs show activity  
✅ Test suite passes all tests  

## 🚦 Next Steps

1. ✅ Verify SMTP configuration
2. ✅ Run test suite
3. ✅ Check email delivery
4. ✅ Test error handling
5. ✅ Monitor email logs
6. ✅ Integrate with production

---

**Ready to go!** The email notification system is fully configured and tested. Start the backend and begin testing!

```powershell
# Start backend
cd backend
php -S localhost:3000

# In another terminal, run tests
.\test-email-notifications.ps1
```

**Questions?** Check `EMAIL_SYSTEM_DOCUMENTATION.md` for complete documentation.
