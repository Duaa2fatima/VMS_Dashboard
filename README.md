# Child Vaccination Management System (VaxCare)

A comprehensive PHP & MySQL multi-portal system built using Bootstrap 5 and the Kaiadmin dashboard theme, conforming 100% to the `child_vaccination` normalized database schema.

---

## 🚀 Quick Setup & Installation

### 1. Requirements
- **XAMPP / WAMP / LAMP** with Apache and MySQL running.
- **PHP 8.x** or higher.

### 2. Database Initialization
1. Ensure MySQL is running in XAMPP Control Panel.
2. Open your web browser and visit:
   ```
   http://localhost/vaccination/vaccination/database/setup.php
   ```
3. Click the **"(Re)Install Database & Seed Data"** button. This will automatically:
   - Create the `child_vaccination` database.
   - Create all 8 normalized tables with foreign key constraints.
   - Populate realistic seed data for immediate testing.

---

## 🔐 Portal Login Credentials

| Portal | Role | Username | Password | Email |
| :--- | :--- | :--- | :--- | :--- |
| **Admin Portal** | System Administrator | `admin` | `admin123` | `admin@vaccination.gov` |
| **Admin Portal** | Healthcare Supervisor | `supervisor` | `admin123` | `supervisor@vaccination.gov` |
| **Parent Portal** | Registered Parent | `parent1` / `Usman10` | `admin123` / `usman123` | `robert.jenkins@example.com` |
| **Hospital Portal** | Healthcare Center | `hospital1` / `cityHospital` | `admin123` / `city123` | `info@citychildrenhospital.org` |

---

## 📂 Database Schema (`child_vaccination`)

The system maps directly to all 8 tables in the schema:
1. `roles` (`role_id`, `role_name`: Admin, Parent, Hospital)
2. `admins` (`admin_id`, `role_id`, `name`, `email`, `username`, `password`, `status`)
3. `parents` (`parent_id`, `role_id`, `name`, `email`, `phone`, `username`, `password`, `address`)
4. `hospitals` (`hospital_id`, `role_id`, `hospital_name`, `address`, `location`, `phone`, `email`, `username`, `password`, `status`)
5. `children` (`child_id`, `parent_id`, `child_name`, `gender`, `date_of_birth`, `address`, `notes`)
6. `vaccines` (`vaccine_id`, `vaccine_name`, `description`, `age_group`, `stock_status`: 'Available', 'Unavailable')
7. `bookings` (`booking_id`, `child_id`, `hospital_id`, `vaccine_id`, `admin_id`, `booking_date`, `appointment_date`, `status`, `approval_date`)
8. `vaccination_records` (`record_id`, `booking_id`, `child_id`, `vaccination_date`, `status`, `remarks`)

---

## 👨‍👩‍👧 Parent Panel (`parent/`)

Designed with the Kaiadmin dashboard aesthetic, responsive sidebar, stat cards, and dark/light mode toggle:

### 1. Parent Dashboard (`parent/index.php`)
- **Key Metrics**: Registered Children, Upcoming Vaccination Dates (next 30 days), Pending Booking Approvals, Completed Vaccinations.
- **Vaccination Dates / Notification Alert Section**: Prominent notification box highlighting imminent approved appointments (with countdown badges: Today, Tomorrow, In X days) and upcoming WHO immunization milestone due dates.
- **My Children Cards**: Quick cards with infant age, DOB, doses received, and progress bar.
- **Recent Bookings Queue**: Quick status tracking for recent appointment requests.
- **Quick Shortcuts**: Add child, book hospital, view schedule, download reports.

### 2. Details of Child (`parent/children.php` & `parent/child-details.php`)
- **Update and Maintain Child Details**: List of parent's children with demographics, DOB, calculated age, gender, address, and medical notes.
- **Add Child Profile Modal**: Easily register new infants with name, gender, DOB, address, and allergies.
- **Edit Child Details Modal**: Update child information and medical restrictions.
- **Delete Child**: Safe removal with confirmation.
- **Child Immunization Profile (`child-details.php`)**: Full demographics, protection level progress bar, comprehensive WHO standard milestone comparison table (Completed, Scheduled, or Due with 1-click booking), and complete clinical records log.

### 3. Vaccination Dates / Upcoming Schedule (`parent/schedule.php`)
- Upcoming date of vaccination for all parent's children.
- Multi-dimensional filters: Filter by specific child and timeframe (**Next 7 Days**, **Next 30 Days**, **Next 90 Days**, **All Future Dates**).
- Displays approved appointments at hospitals alongside calculated milestone due dates.

### 4. Book Hospital (`parent/book-appointment.php`)
- **Search Hospital List**: Filter accredited healthcare facilities by name, location/district, or address.
- **Interactive Booking Form**: Select child, choose hospital, select available vaccine, and choose appointment date.
- Submits booking request with status `Pending` for administrative review.

### 5. My Bookings (`parent/bookings.php`)
- Status tabs: **All**, **Pending Approval**, **Approved / Scheduled**, **Completed**, **Rejected**.
- Action to cancel pending booking requests.
- Information modal showing full appointment details, hospital address, and clinical outcome.

### 6. Report of Vaccination Taken (`parent/reports.php`)
- Clinical reports of previous vaccinations received by infants.
- Multi-filter: By child, vaccine, date range, and status (`Vaccinated` vs `Not Vaccinated`).
- Summary breakdown (Total logs, Vaccinated, Deferred).
- **Print Report / Certificate**: Dedicated `@media print` layout with "Print Immunization Report" button.
- **Export to CSV**: Download vaccination records for pediatrician or school admission.

### 7. Parent Profile (`parent/profile.php`)
- View and update full name, contact phone, residential address, and change password.

---

## 🏥 Hospital Panel (`hospital/`)

Designed specifically for healthcare facilities and clinical staff, matching the Kaiadmin admin panel:

### 1. Hospital Registration & Login
- Hospitals register with name, physical address, location/district, official email, phone, username, and password.
- Seamless authentication routing directly to the Hospital Dashboard.

### 2. Hospital Dashboard (`hospital/index.php`)
- Facility header displaying hospital name, accreditation status, location, and address.
- **KPI Metrics**: Today's Appointments, Approved / Received Appointments, Completed Doses Administered, Total Patients Served.
- **Today's Patient Schedule Queue**: Priority action table for infants arriving today for immunization, with direct **"Update Status"** button.
- Recent vaccination activity log and quick action shortcuts.

### 3. Received Appointments & Update Vaccine Status (`hospital/appointments.php`)
- Hospital receives appointments once booked and approved from the admin side.
- Quick filter tabs: **Approved / Received**, **Today's Schedule**, **Completed**, **All Appointments**.
- Search by child name, parent phone, vaccine, and scheduled date.
- **Update Vaccine Status Modal**:
  - Sets status: **`Vaccinated`** or **`Not Vaccinated`**.
  - Sets Date of Administration / Record.
  - Adds clinical remarks, vaccine manufacturer batch number, dosage site, or reason why vaccination was deferred (e.g. fever, illness).
  - Automatically records into `vaccination_records` and updates booking status to `Completed`.

### 4. Vaccination Records Log (`hospital/records.php`)
- Master clinical history of all vaccinations performed at the facility.
- Filter by vaccine, status, and date range.
- **Export to CSV** and **Print Log Sheet** functions.

### 5. Available Vaccines Reference (`hospital/vaccines.php`)
- Catalog of all pediatric vaccines, recommended age groups, target diseases, and stock availability.

### 6. Facility Profile & Location Settings (`hospital/profile.php`)
- Manage facility name, location district, physical address, phone, email, and security credentials.

---

## 🛡️ Admin Panel (`admin/`)
- System dashboard with comprehensive statistics.
- All child details, parent registration directory, hospital directory & requests approval.
- Date of vaccination tracking and parent booking requests review (Approve / Reject).
- Vaccine inventory catalog and date-wise vaccination reporting.
