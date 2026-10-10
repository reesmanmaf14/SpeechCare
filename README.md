# 🗣️ SpeechCare — Web-Based System for Speech Therapy Clinic

**Helping therapists, parents, and administrators manage speech therapy care in one place.**

SpeechCare is a web-based speech therapy clinic management system developed to simplify clinic operations, manage therapist availability, coordinate appointments, and monitor patient progress. The platform provides a centralized environment for therapists, parents, and administrators to manage essential therapy-related activities efficiently.

## ✨ Key Features

- 🔐 **User Authentication** — Secure user authentication and account management.
- 📧 **Email Verification** — OTP-based email verification to support account security.
- 👩‍⚕️ **Therapist Availability** — Manage therapist availability and available appointment slots.
- 📅 **Appointment Management** — Organize therapy sessions based on therapist availability.
- 🔔 **Notifications** — Keep users informed about relevant updates.
- 📈 **Patient Progress Tracking** — Maintain therapy progress notes and monitor patient development.
- 👥 **Role-Based Access** — Support different user roles, including administrators, therapists, and parents.

## 🛠️ Tech Stack

**Frontend**
- HTML5
- CSS3
- JavaScript
- Bootstrap

**Backend**
- PHP

**Database**
- MySQL

**Additional Tools**
- PHPMailer for email functionality
- Git and GitHub for version control

## 🏗️ System Overview

SpeechCare brings together the essential workflows of a speech therapy clinic:

1. Users authenticate and access the system.
2. Therapists manage their availability.
3. Appointments are organized around available time slots.
4. Notifications help communicate relevant updates.
5. Patient progress notes support ongoing therapy management.

## 🚀 Getting Started

Follow these steps to run the project locally.

### Prerequisites

Install the following tools:

- PHP
- MySQL
- Apache server (XAMPP recommended)
- Composer, if required by the project dependencies
- Git

### Installation

**1. Clone the repository**

```bash
git clone https://github.com/reesmanmaf14/SpeechCare
```

**2. Navigate to the project directory**

```bash
cd SpeechCare
```

**3. Set up the local server**

Place the project in your XAMPP `htdocs` directory and start Apache and MySQL using the XAMPP Control Panel.

**4. Configure the database**

- Open phpMyAdmin.
- Create a MySQL database for SpeechCare.
- Import the project's SQL database file, if provided.
- Update the database connection settings in the project's configuration file.

**5. Configure email functionality**

Configure the PHPMailer SMTP settings with the appropriate email credentials if email verification and notifications require SMTP.

**6. Run the application**

Open the project through your local Apache server in your browser using the appropriate project URL.

> Note: Update the setup instructions to match the actual database file, configuration filenames, and project directory structure in your repository.

## 🔒 Security Considerations

- Keep database credentials and SMTP credentials private.
- Do not commit passwords, API keys, or other sensitive configuration values.
- Validate and sanitize user input on the server.
- Enforce appropriate access permissions for each user role.

## 🎯 Project Goals

SpeechCare aims to improve the organization of speech therapy clinic activities by bringing therapist scheduling, appointment coordination, notifications, and patient progress information into a single web application.

## 📄 License

This project is developed for academic and portfolio purposes. Add a license file if you intend to distribute the project under a specific open-source license.
