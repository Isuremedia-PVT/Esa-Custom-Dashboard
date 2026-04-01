# Esa Custom Dashboard

A comprehensive, shift-based Patient Assessment and Logging System designed to streamline patient records, staff assignments, and dynamic questionnaire management.

## Features

*   **Multi-Role Access Control:** Secure, role-based interfaces for Admins, Staff, and Patients.
*   **Dynamic Shift Assignments:** Questionnaires and logs are intelligently filtered and assigned based on customized medical shifts.
*   **Smart Assessment Management:** Admins can create dynamic, multi-format medical questions, assign them per patient shift, and collect essential data via the API.
*   **Comprehensive Logging:** Robust chronological assessment logs, draft-saving capabilities, and real-time form data retrieval for seamless patient handover.
*   **Secure Document Uploads:** Secure storage and retrieval of medical documents and image submissions, neatly bound to patient assignments.

## Architecture

This project is built using:
- **Frontend:** Vanilla JS / jQuery with a premium modern CSS design (Select2, SweetAlert2 utilized).
- **Backend:** Object-Oriented PHP 8+ with PDO for database management.
- **Database:** MariaDB/MySQL (Schema included in `ukblin1_esa.sql`).

## Local Setup Instructions

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/Isuremedia-PVT/Esa-Custom-Dashboard.git
   ```

2. **Database Configuration:**
   - Import the included database structure & mock data using `ukblin1_esa.sql`.
   - Create a `.env` file in the root directory and update your local database credentials:
     ```env
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=patient_record_management
     ```

3. **Deploy:**
   - Host the project on any standard Apache/Nginx server (e.g., XAMPP/WAMP for local testing).
   - Ensure the `public/Uploads/` directory has write permissions for image and document uploads.

## Support 
Maintained by Isuremedia-PVT.
