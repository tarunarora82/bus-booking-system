# Email Notification System - Complete Documentation

## 📧 Overview

The Bus Booking System now includes a fully functional email notification system that automatically sends booking confirmations and cancellation emails to employees based on their employee ID and email address from the employee list.

## ✨ Features

- ✅ Automatic email notifications on booking creation
- ✅ Automatic email notifications on booking cancellation  
- ✅ SMTP configuration for Intel email server
- ✅ Unique booking ID in all emails
- ✅ Complete booking details in emails
- ✅ Professional HTML email templates
- ✅ Error handling for missing/invalid email addresses
- ✅ Email activity logging
- ✅ Fallback to employee email if system email fails

## 🔧 Configuration

### SMTP Settings

The system is pre-configured with Intel SMTP server details:

```
SMTP Server: smtpauth.intel.com
SMTP Port: 587
Security: TLS
From Email: sys_github01@intel.com
From Name: Bus Booking System
```

**Configuration File**: `backend/config/email.php`

### Email Credentials

```php
SMTP_USERNAME=sys_github01@intel.com
SMTP_PASSWORD=dateAug21st2025!@#$%
```

## 📁 File Structure

```
bus-booking-system/
├── backend/
│   ├── EmailService.php           # Email service class
│   ├── config/
│   │   └── email.php              # Email configuration
│   └── simple-api.php             # Updated API with email integration
└── test-email-notifications.html  # Comprehensive test suite
```

## 🚀 How It Works

### 1. Booking Creation Flow

When a booking is created:

1. System validates employee ID and bus availability
2. Creates booking with unique booking ID (format: `BK20251007XXXX`)
3. Retrieves employee email from employee list
4. If email exists and is valid:
   - Sends confirmation email with all booking details
   - Logs email activity
   - Returns success status
5. If email is missing or invalid:
   - Completes booking anyway
   - Logs warning
   - Returns skip reason in response

### 2. Booking Cancellation Flow

When a booking is cancelled:

1. System validates and cancels the booking
2. Retrieves employee email from employee list
3. If email exists and is valid:
   - Sends cancellation email
   - Logs email activity
   - Returns success status
4. If email is missing or invalid:
   - Completes cancellation anyway
   - Logs warning
   - Returns skip reason in response

### 3. Employee Email Lookup

The system looks up employee email addresses from the employee list:

```php
// Employee structure
{
    "employee_id": "11453732",
    "name": "John Doe",
    "email": "john.doe@intel.com",
    "department": "Engineering"
}
```

If an employee doesn't have an email in the system, the booking still succeeds but no email is sent.

## 📧 Email Templates

### Booking Confirmation Email

**Subject**: `Bus Booking Confirmation - {BOOKING_ID}`

**Includes**:
- ✅ Unique Booking ID
- 👤 Employee Name & ID
- 🚌 Bus Number
- 📍 Route Details
- 📅 Schedule Date
- 🕐 Departure Time
- 🌅 Slot (Morning/Evening/Night)
- ⏰ Booking Timestamp
- ⚠️ Important Guidelines
- 🔔 Departure Timeline

### Booking Cancellation Email

**Subject**: `Bus Booking Cancellation - {BOOKING_ID}`

**Includes**:
- ❌ Cancellation Status
- 🎫 Original Booking ID
- 👤 Employee Details
- 🚌 Bus Information
- 📅 Original Schedule
- ⏰ Cancellation Timestamp
- ℹ️ Next Steps Information

## 🧪 Testing

### Test Suite

Open `test-email-notifications.html` in your browser to access the comprehensive test suite.

**Test Cases**:

1. **Test Booking Email**
   - Creates a booking with valid employee
   - Verifies email is sent
   - Displays booking confirmation

2. **Test Cancellation Email**
   - Cancels an existing booking
   - Verifies cancellation email
   - Shows cancellation details

3. **Test Missing Email**
   - Creates booking for employee without email
   - Verifies error handling
   - Confirms booking still succeeds

4. **View Email Log**
   - Shows all email activity
   - Real-time log monitoring
   - Auto-refresh every 10 seconds

### Manual Testing

#### Create Booking with Email
```bash
curl -X POST http://localhost:3000/booking/create \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "11453732",
    "bus_number": "BUS001",
    "schedule_date": "2025-10-07"
  }'
```

#### Cancel Booking with Email
```bash
curl -X POST http://localhost:3000/booking/cancel \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "11453732",
    "bus_number": "BUS001"
  }'
```

#### View Email Log
```bash
curl http://localhost:3000/admin/email-log
```

## 📊 API Response Examples

### Successful Booking with Email

```json
{
  "status": "success",
  "message": "Booking confirmed successfully",
  "data": {
    "booking_id": "BK202510070001",
    "employee_id": "11453732",
    "employee_name": "John Doe",
    "bus_number": "BUS001",
    "route": "City Center to Industrial Park",
    "schedule_date": "2025-10-07",
    "departure_time": "08:00 AM",
    "slot": "morning",
    "status": "confirmed",
    "created_at": "2025-10-07 10:30:00"
  },
  "employee": {
    "employee_id": "11453732",
    "name": "John Doe",
    "email": "john.doe@intel.com",
    "department": "Engineering"
  },
  "email_notification": "Confirmation email sent to john.doe@intel.com",
  "email_sent": true
}
```

### Booking with Missing Email

```json
{
  "status": "success",
  "message": "Booking confirmed successfully",
  "data": {
    "booking_id": "BK202510070002",
    ...
  },
  "email_notification": "Email not sent - No email address available for employee",
  "email_sent": false,
  "email_skip_reason": "missing_email"
}
```

## 📝 Email Log Format

Email activity is logged to `/tmp/bus_bookings/email_log.txt`:

```
[2025-10-07 10:30:00] [SUCCESS] Booking confirmation sent to john.doe@intel.com for booking BK202510070001
[2025-10-07 10:31:00] [WARNING] No email address found for employee: NO_EMAIL_EMP
[2025-10-07 10:32:00] [ERROR] Invalid email address for employee: 12345 - invalid@
[2025-10-07 10:33:00] [SUCCESS] Booking cancellation sent to john.doe@intel.com for booking BK202510070001
```

## 🔒 Error Handling

### Missing Email Address

**Scenario**: Employee record exists but has no email field

**Behavior**:
- Booking proceeds normally
- Email is NOT sent
- Log entry: `[WARNING] No email address found for employee: {ID}`
- API response includes: `"email_skip_reason": "missing_email"`

### Invalid Email Address

**Scenario**: Email field exists but format is invalid

**Behavior**:
- Booking proceeds normally
- Email is NOT sent
- Log entry: `[ERROR] Invalid email address for employee: {ID} - {email}`
- API response includes: `"email_skip_reason": "invalid_email"`

### SMTP Connection Failure

**Scenario**: Cannot connect to SMTP server

**Behavior**:
- Booking proceeds normally
- Email send fails
- Log entry: `[ERROR] Failed to send email to {email} for booking {ID}`
- API response: `"email_sent": false`

## 🔍 Monitoring & Debugging

### View Email Logs

**Via Web UI**:
1. Open `test-email-notifications.html`
2. Click "View Email Log" button
3. Check "Real-time Email Log Monitor" section

**Via API**:
```bash
curl http://localhost:3000/admin/email-log
```

**Direct File Access**:
```bash
cat /tmp/bus_bookings/email_log.txt
```

### Common Issues

#### Email Not Sending

1. **Check SMTP Configuration**
   - Verify `backend/config/email.php` settings
   - Ensure SMTP server is reachable
   - Test credentials

2. **Check Employee Email**
   - Verify employee has valid email in system
   - Check email format

3. **Check PHP mail() Function**
   - Ensure PHP is configured for mail
   - Check `php.ini` SMTP settings

4. **Check Firewall/Network**
   - Verify port 587 is open
   - Check corporate proxy settings
   - Test SMTP connectivity

#### Email Going to Spam

1. Add `sys_github01@intel.com` to safe senders
2. Check SPF/DKIM configuration
3. Verify email content not triggering spam filters

## 🎯 Best Practices

### For Employees

1. **Ensure Email Address**: Make sure your employee profile has a valid email address
2. **Check Spam Folder**: First-time emails might go to spam
3. **Save Booking ID**: Keep the booking ID from confirmation email for reference

### For Administrators

1. **Monitor Email Log**: Regularly check email activity log
2. **Validate Employee Data**: Ensure all employees have valid email addresses
3. **Test Email System**: Periodically test email functionality
4. **Backup Configuration**: Keep SMTP credentials secure and backed up

## 📈 Future Enhancements

Potential improvements for the email system:

- [ ] HTML email preview in UI
- [ ] Email template customization
- [ ] Multiple email recipients (CC/BCC)
- [ ] Email queue for batch processing
- [ ] Retry logic for failed emails
- [ ] Email analytics dashboard
- [ ] SMS notifications integration
- [ ] Push notifications support

## 🆘 Support

For issues or questions:

1. Check email activity log
2. Verify employee email in system
3. Test SMTP connectivity
4. Review error messages in API response
5. Check `/tmp/bus_bookings/email_log.txt`

## ✅ Verification Checklist

- [x] SMTP configuration set correctly
- [x] Email service class implemented
- [x] Booking API integrated with email
- [x] Cancellation API integrated with email
- [x] Error handling for missing emails
- [x] Error handling for invalid emails
- [x] Unique booking ID in emails
- [x] Complete booking details in emails
- [x] Professional HTML templates
- [x] Email activity logging
- [x] Test suite created
- [x] Documentation complete

## 📄 License

This email notification system is part of the Bus Booking System and follows the same license.

---

**Version**: 1.0.0  
**Last Updated**: October 7, 2025  
**Author**: Bus Booking System Team
